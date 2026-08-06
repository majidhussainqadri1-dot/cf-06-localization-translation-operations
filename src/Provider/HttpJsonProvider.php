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
        $url=(string)($this->config['url']??'');$hosts=is_array($this->config['allowed_hosts']??null)?$this->config['allowed_hosts']:[];UrlGuard::assertPublicHttps($url,$hosts);
        $body=wp_json_encode(['job'=>$job,'units'=>$units],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($body)||strlen($body)>5_000_000){throw new RuntimeException('Machine translation request is invalid or oversized.');}
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
        $hosts=is_array($this->config['allowed_hosts']??null)?$this->config['allowed_hosts']:[];UrlGuard::assertPublicHttps($purge,$hosts);
        $response=wp_remote_request($purge,['method'=>'DELETE','timeout'=>20,'redirection'=>0,'sslverify'=>true,'reject_unsafe_urls'=>true,
            'headers'=>['Authorization'=>'Bearer '.$this->credential(),'Content-Type'=>'application/json'],'body'=>wp_json_encode(['reference'=>$providerReference])]);
        $code=is_wp_error($response)?0:(int)wp_remote_retrieve_response_code($response);$raw=is_wp_error($response)?'':(string)wp_remote_retrieve_body($response);
        if(is_wp_error($response)||$code<200||$code>=300||strlen($raw)>262144){throw new RuntimeException('Provider purge could not be verified.');}
        return ['status'=>'purged','verified_at'=>gmdate(DATE_ATOM),'response_hash'=>hash('sha256',$raw)];
    }

    public function health():array{return ['status'=>''!==($this->config['url']??'')?'configured':'unknown','region'=>(string)($this->config['region']??''),'training_allowed'=>false];}
    private function credential():string{$env=(string)($this->config['credential_env']??'');if(1!==preg_match('/^[A-Z][A-Z0-9_]{2,127}$/D',$env)){throw new RuntimeException('Provider credential reference is invalid.');}$value=(string)getenv($env);if(''===$value){throw new RuntimeException('Provider credential is unavailable.');}return $value;}
}
