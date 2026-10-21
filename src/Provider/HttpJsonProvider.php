<?php

declare(strict_types=1);

namespace Sabri\Localization\Provider;

use RuntimeException;
use Sabri\Localization\Domain\Security\UrlGuard;

final class HttpJsonProvider implements MachineTranslationProvider
{
    public function __construct(private readonly array $config){}
    public function key():string{return (string)($this->config['key']??'http-json');}

    public function submit(array $job,array $units): array
    {
        $url=(string)($this->config['url']??'');$hosts=$this->allowedHosts();UrlGuard::assertPublicHttps($url,$hosts);
        // The repository job row contains internal actor, audit and retention fields.
        // Only the provider's allowlisted transport envelope may leave CF-06.
        // All translation source material has already passed the C1/low-risk
        // redaction gate before it reaches this adapter.
        $providerJob = [
            'uuid'=>(string)($job['uuid']??''),
            'provider_key'=>(string)($job['provider_key']??''),
            'region_code'=>(string)($job['region_code']??''),
            'purpose'=>(string)($job['purpose']??''),
            'outbound_hash'=>(string)($job['outbound_hash']??''),
        ];
        if (1!==preg_match('/^[a-f0-9-]{36}$/D',$providerJob['uuid'])
            || 1!==preg_match('/^[a-f0-9]{64}$/D',$providerJob['outbound_hash'])
            || ''===$providerJob['provider_key'] || ''===$providerJob['region_code']
            || ''===$providerJob['purpose']) {
            throw new RuntimeException('Machine translation provider envelope is incomplete.');
        }
        $body=wp_json_encode(['job'=>$providerJob,'units'=>$units],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($body)||strlen($body)>5_000_000){throw new RuntimeException('Machine translation request is invalid or oversized.');}
        $response=wp_remote_post($url,['timeout'=>20,'redirection'=>0,'sslverify'=>true,'reject_unsafe_urls'=>true,
            'headers'=>['Authorization'=>'Bearer '.$this->credential(),'Content-Type'=>'application/json','Idempotency-Key'=>(string)$job['uuid']],
            'body'=>$body,'data_format'=>'body']);
        if(is_wp_error($response)){throw new RuntimeException('Machine translation provider request failed.');}
        $code=(int)wp_remote_retrieve_response_code($response);$raw=(string)wp_remote_retrieve_body($response);
        if($code<200||$code>=300||strlen($raw)>5_000_000){throw new RuntimeException('Machine translation provider returned an unsafe response.');}
        $decoded=json_decode($raw,true,128,JSON_THROW_ON_ERROR);if(!is_array($decoded)){throw new RuntimeException('Machine translation provider returned invalid JSON.');}return $decoded;
    }

    public function purge(string $providerReference): array
    {
        $providerReference=trim($providerReference);if(''===$providerReference||strlen($providerReference)>191){throw new RuntimeException('Provider purge reference is invalid.');}
        $purge=(string)($this->config['purge_url']??'');if(''===$purge){return ['status'=>'manual_evidence_required','reference_hash'=>hash('sha256',$providerReference)];}
        $hosts=$this->allowedHosts();UrlGuard::assertPublicHttps($purge,$hosts);
        $response=wp_remote_request($purge,['method'=>'DELETE','timeout'=>20,'redirection'=>0,'sslverify'=>true,'reject_unsafe_urls'=>true,
            'headers'=>['Authorization'=>'Bearer '.$this->credential(),'Content-Type'=>'application/json'],'body'=>wp_json_encode(['reference'=>$providerReference])]);
        $code=is_wp_error($response)?0:(int)wp_remote_retrieve_response_code($response);$raw=is_wp_error($response)?'':(string)wp_remote_retrieve_body($response);
        if(is_wp_error($response)||strlen($raw)>262144){throw new RuntimeException('Provider purge could not be verified.');}
        // HTTP 202 merely queues a deletion request. It cannot be treated as
        // completed erasure or as eligible evidence for a local "purged" state.
        // A successful 204 is a completed DELETE with no representation; a
        // 200 response must explicitly attest completed deletion for this ref.
        if (204 !== $code) {
            if (200 !== $code) {
                throw new RuntimeException('Provider purge is not confirmed complete.');
            }
            try {
                $proof=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            } catch (\\JsonException $exception) {
                throw new RuntimeException('Provider purge completion evidence is invalid.',0,$exception);
            }
            if (!is_array($proof) || !in_array((string)($proof['status']??''),['purged','deleted'],true)
                || !hash_equals($providerReference,(string)($proof['reference']??''))) {
                throw new RuntimeException('Provider did not attest completed deletion for the exact reference.');
            }
        }
        return [
            'status'=>'purged',
            'provider_http_status'=>$code,
            'reference_hash'=>hash('sha256',$providerReference),
            'verified_at'=>gmdate(DATE_ATOM),
            'response_hash'=>hash('sha256',$raw),
        ];
    }

    public function health():array
    {
        $url=(string)($this->config['url']??'');
        $credentialEnv=(string)($this->config['credential_env']??'');
        return [
            'status'=>''!==$url?'configured':'unknown',
            'region'=>(string)($this->config['region']??''),
            'training_allowed'=>false,
            'base_url'=>$url,
            'allowed_hosts'=>$this->allowedHosts(),
            'credential_reference'=>''!==$credentialEnv?'env:'.$credentialEnv:'',
            'contract_version'=>(string)($this->config['contract_version']??''),
        ];
    }

    private function allowedHosts():array
    {
        $hosts=is_array($this->config['allowed_hosts']??null)?$this->config['allowed_hosts']:[];
        $normalized=[];
        foreach($hosts as $host){$host=strtolower(rtrim(trim((string)$host),'.'));if(''!==$host){$normalized[]=$host;}}
        $normalized=array_values(array_unique($normalized));sort($normalized,SORT_STRING);return $normalized;
    }

    private function credential():string
    {
        $env=(string)($this->config['credential_env']??'');
        if(1!==preg_match('/^[A-Z][A-Z0-9_]{2,127}$/D',$env)){throw new RuntimeException('Provider credential reference is invalid.');}
        $value=(string)getenv($env);
        if(''===$value||strlen($value)>4096||preg_match('/[\x00-\x1F\x7F]/',$value)){
            throw new RuntimeException('Provider credential is unavailable or unsafe for an HTTP authorization header.');
        }
        return $value;
    }
}
