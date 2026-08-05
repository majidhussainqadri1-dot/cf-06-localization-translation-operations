<?php

declare(strict_types=1);

namespace Sabri\Localization\Provider;

use RuntimeException;
use Sabri\Localization\Domain\Security\UrlGuard;

final class HttpJsonProvider implements MachineTranslationProvider
{
    public function __construct(private readonly array $config)
    {
    }

    public function key(): string { return (string)($this->config['key'] ?? 'http-json'); }

    public function submit(array $job, array $units): array
    {
        $url=(string)($this->config['url'] ?? '');
        $hosts=is_array($this->config['allowed_hosts'] ?? null)?$this->config['allowed_hosts']:array();
        UrlGuard::assertPublicHttps($url,$hosts);
        $credential=$this->credential();
        $response=wp_remote_post($url,array(
            'timeout'=>20,'redirection'=>0,'sslverify'=>true,
            'headers'=>array('Authorization'=>'Bearer '.$credential,'Content-Type'=>'application/json','Idempotency-Key'=>(string)$job['uuid']),
            'body'=>wp_json_encode(array('job'=>$job,'units'=>$units),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'data_format'=>'body',
        ));
        if (is_wp_error($response)) {
            throw new RuntimeException('Machine translation provider request failed.');
        }
        $code=(int)wp_remote_retrieve_response_code($response);
        $body=(string)wp_remote_retrieve_body($response);
        if ($code<200 || $code>=300 || strlen($body)>5_000_000) {
            throw new RuntimeException('Machine translation provider returned an unsafe response.');
        }
        $decoded=json_decode($body,true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Machine translation provider returned invalid JSON.');
        }
        return $decoded;
    }

    public function purge(string $providerReference): array
    {
        $purge=(string)($this->config['purge_url'] ?? '');
        if (''===$purge) { return array('status'=>'manual_evidence_required'); }
        UrlGuard::assertPublicHttps($purge,is_array($this->config['allowed_hosts']??null)?$this->config['allowed_hosts']:array());
        $response=wp_remote_request($purge,array('method'=>'DELETE','timeout'=>20,'redirection'=>0,'sslverify'=>true,'headers'=>array('Authorization'=>'Bearer '.$this->credential(),'Content-Type'=>'application/json'),'body'=>wp_json_encode(array('reference'=>$providerReference))));
        if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response)>=300) {
            throw new RuntimeException('Provider purge could not be verified.');
        }
        return array('status'=>'purged','verified_at'=>gmdate(DATE_ATOM));
    }

    public function health(): array
    {
        return array('status'=>''!==($this->config['url']??'')?'configured':'unknown','region'=>(string)($this->config['region']??''),'training_allowed'=>false);
    }

    private function credential(): string
    {
        $env=(string)($this->config['credential_env']??'');
        $value=''!==$env?(string)getenv($env):'';
        if (''===$value) { throw new RuntimeException('Provider credential is unavailable.'); }
        return $value;
    }
}
