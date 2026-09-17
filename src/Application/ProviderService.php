<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Security\UrlGuard;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ProviderService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Transaction $tx
    ) {}

    public function register(array $input): array
    {
        $key=sanitize_key((string)($input['provider_key']??''));
        $type=sanitize_key((string)($input['provider_type']??'mt'));
        $url=esc_url_raw((string)($input['base_url']??''));
        $hosts=[];
        foreach(is_array($input['allowed_hosts']??null)?$input['allowed_hosts']:[] as $host){
            $host=strtolower(rtrim(trim((string)$host),'.'));
            if(1===preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D',$host)){$hosts[]=$host;}
        }
        $hosts=array_values(array_unique($hosts));sort($hosts,SORT_STRING);if(count($hosts)>100){throw new InvalidArgumentException('Provider host allowlist exceeds the bounded limit.');}
        if(''===$key||strlen($key)>80||''===$type||strlen($type)>40){throw new InvalidArgumentException('Provider identity is required and must remain bounded.');}
        if(''!==$url){UrlGuard::assertPublicHttps($url,$hosts);}
        if(!empty($input['training_allowed'])){throw new InvalidArgumentException('Provider training is denied by default.');}
        $credentialRef=sanitize_text_field((string)($input['credential_reference']??''));
        if(''!==$credentialRef&&1!==preg_match('/^env:[A-Z][A-Z0-9_]{2,127}$/D',$credentialRef)){throw new InvalidArgumentException('Provider credentials must use a bounded environment reference.');}
        $contractVersion=sanitize_text_field((string)($input['contract_version']??''));
        if(1!==preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/D',$contractVersion)){throw new InvalidArgumentException('Provider contract version is invalid.');}
        $region=sanitize_text_field((string)($input['region']??''));
        if(''!==$region&&1!==preg_match('/^[A-Za-z0-9-]{2,32}$/D',$region)){throw new InvalidArgumentException('Provider region code is invalid.');}
        $subprocessors=[];foreach(array_slice(is_array($input['subprocessors']??null)?$input['subprocessors']:[],0,100) as $sub){$sub=sanitize_text_field((string)$sub);if(''!==$sub){if(strlen($sub)>191){throw new InvalidArgumentException('Provider subprocessor identifier exceeds the bounded limit.');}$subprocessors[]=$sub;}}
        $subprocessors=array_values(array_unique($subprocessors));
        $hostsJson=wp_json_encode($hosts);$subsJson=wp_json_encode($subprocessors);if(!is_string($hostsJson)||!is_string($subsJson)||strlen($subsJson)>65535){throw new InvalidArgumentException('Provider metadata could not be encoded within governed bounds.');}
        return $this->tx->run(function() use ($input,$key,$type,$url,$hostsJson,$subsJson,$credentialRef,$contractVersion,$region): array {
            $existing=$this->repo->findOne('providers','provider_key',$key);
            $data=array('provider_key'=>$key,'provider_type'=>$type,'base_url'=>$url?:null,'allowed_hosts'=>$hostsJson,
                'region_code'=>$region?:null,'retention_days'=>max(0,min(30,(int)($input['retention_days']??0))),
                'training_allowed'=>0,'subprocessors_json'=>$subsJson,'credential_reference'=>$credentialRef?:null,'contract_version'=>$contractVersion);
            if(is_array($existing)&&'active'===(string)$existing['status']){
                foreach($data as $field=>$value){
                    if((string)($existing[$field]??'')!==(string)($value??'')){throw new InvalidArgumentException('An active provider must be disabled before governance-relevant configuration is changed.');}
                }
                return $existing;
            }
            if(is_array($existing)){
                if(!array_key_exists('row_version',$input)||(int)$input['row_version']<=0){throw new InvalidArgumentException('Provider update requires an explicit current row_version.');}
                $data['status']=$existing['status'];$row=$this->repo->updateVersioned('providers',(string)$existing['uuid'],(int)$input['row_version'],$data);
            }else{$data['status']='disabled';$row=$this->repo->insert('providers',array_merge($data,array('row_version'=>1)));}
            $this->audit->record('provider',$key,'localization_provider_registered','success',array('type'=>$type,'region'=>$data['region_code'],'retention_days'=>$data['retention_days'],'status'=>$row['status']));
            return $row;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''): array
    {
        $row=$this->repo->find('providers',$uuid)??throw new InvalidArgumentException('Localization provider not found.');
        $map=['disabled'=>['approved'],'approved'=>['active','disabled','deprecated'],'active'=>['disabled','deprecated'],'deprecated'=>['disabled']];
        if(!in_array($to,$map[(string)$row['status']]??[],true)){throw new InvalidArgumentException('Invalid provider transition.');}
        $reason=sanitize_textarea_field($reason);if(strlen($reason)>2000){throw new InvalidArgumentException('Provider transition reason exceeds the bounded limit.');}
        if('active'===$to){
            if(!defined('SLTO_PROVIDER_ACTIVATION_APPROVED')||true!==SLTO_PROVIDER_ACTIVATION_APPROVED){throw new InvalidArgumentException('Provider activation has not received Founder approval.');}
            if(empty($row['base_url'])||empty($row['allowed_hosts'])||empty($row['credential_reference'])||empty($row['contract_version'])||empty($row['region_code'])){throw new InvalidArgumentException('Provider activation prerequisites are incomplete.');}
            if(0!==(int)($row['training_allowed']??0)){throw new InvalidArgumentException('Provider training reuse must remain disabled.');}
            $evidence=apply_filters('slto_verify_provider_activation_evidence',false,$row);
            if(true!==$evidence){throw new InvalidArgumentException('Provider privacy/security/region/retention/exit evidence is incomplete.');}
        }
        if('deprecated'===$to&&$this->repo->count('vendor_jobs',['provider_key'=>$row['provider_key']],['purged'])>0){throw new InvalidArgumentException('Provider has unpurged jobs and cannot be deprecated.');}
        return $this->tx->run(function() use ($row,$uuid,$to,$version,$reason): array {
            $updated=$this->repo->updateVersioned('providers',$uuid,$version,['status'=>$to]);
            $this->audit->record('provider',(string)$row['provider_key'],'localization_provider_transition','success',['from'=>$row['status'],'to'=>$to,'reason'=>$reason]);
            return $updated;
        });
    }
}
