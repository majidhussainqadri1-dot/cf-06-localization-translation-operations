<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\BidiValidator;
use Sabri\Localization\Domain\Translation\PlaceholderValidator;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Infrastructure\DependencyInvalidator;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class ResourceService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly AuditRepository $audit,private readonly Outbox $outbox,private readonly Transaction $tx){}

    public function register(array $input):array
    {
        $key=(string)($input['resource_key']??'');
        if(strlen($key)>191||1!==preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+){1,15}$/D',$key)){throw new InvalidArgumentException('Resource key must be stable, semantic and namespaced.');}
        $locale=LocaleValidator::canonicalize((string)($input['source_locale']??''));
        if(null===$locale||!$this->repo->findOne('locales','locale_tag',$locale)){throw new InvalidArgumentException('Source locale is not registered.');}
        $text=(string)($input['source_text']??'');if(''===trim($text)||strlen($text)>500000){throw new InvalidArgumentException('Source text is empty or exceeds the bounded limit.');}
        BidiValidator::assertSafe($text,true);
        $risk=strtolower((string)($input['risk_class']??'low'));$data=strtoupper((string)($input['data_class']??'C1'));
        if(!in_array($risk,array('low','medium','high','critical','private'),true)||!in_array($data,array('C1','C2','C3','C4','C5'),true)){throw new InvalidArgumentException('Invalid resource risk or data class.');}
        $domain=sanitize_key((string)($input['domain']??'platform'))?:'platform';if(strlen($domain)>80){throw new InvalidArgumentException('Resource domain exceeds the canonical storage bound.');}
        $schema=PlaceholderValidator::normalizeSchema($input['placeholders']??array());PlaceholderValidator::assertSource($text,$schema);
        $context=wp_kses_post((string)($input['context']??''));
        $description=sanitize_textarea_field((string)($input['description']??''));
        if(strlen($context)>262144||strlen($description)>65535){throw new InvalidArgumentException('Resource descriptive metadata exceeds canonical storage bounds.');}
        $markup=is_array($input['markup_policy']??null)?$input['markup_policy']:array();
        $rawReferences=is_array($input['references']??null)?$input['references']:array();
        if(count($rawReferences)>100){throw new InvalidArgumentException('Resource reference evidence exceeds the bounded item limit.');}
        $references=$rawReferences;
        $contextRefs=$this->normalizeContextRefs($input['context_refs']??array());
        $referenceEnvelope=array('references'=>$references,'context_refs'=>$contextRefs);
        $translatability=is_array($input['translatability']??null)?$input['translatability']:array();
        $markupJson=$this->encodeBoundedMetadata($markup,'markup policy');
        $referencesJson=$this->encodeBoundedMetadata($referenceEnvelope,'reference/context evidence');
        $translatabilityJson=$this->encodeBoundedMetadata($translatability,'translatability evidence');
        $hashPayload=wp_json_encode($this->canonicalize(array(
            'key'=>$key,'locale'=>$locale,'text'=>$text,'context'=>$context,'description'=>$description,
            'domain'=>$domain,'risk'=>$risk,'data'=>$data,'placeholders'=>$schema,'markup_policy'=>$markup,
            'references'=>$references,'context_refs'=>$contextRefs,'translatability'=>$translatability,
        )),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($hashPayload)){throw new InvalidArgumentException('Resource source evidence could not be encoded safely.');}
        $hash=hash('sha256',$hashPayload);
        $existing=$this->repo->findOne('resources','resource_key',$key);
        if(is_array($existing)&&'retired'===(string)($existing['status']??'')){throw new InvalidArgumentException('A retired translatable resource cannot be reactivated through ordinary registration.');}
        if(is_array($existing)&&'active'===(string)$existing['status']&&hash_equals((string)$existing['source_hash'],$hash)){return array('changed'=>false,'record'=>$existing);}
        if(is_array($existing)&&(!array_key_exists('row_version',$input)||(int)$input['row_version']<=0)){throw new InvalidArgumentException('Resource update requires an explicit current row_version.');}

        $result=$this->tx->run(function()use($existing,$key,$locale,$text,$hash,$input,$domain,$risk,$data,$schema,$context,$description,$markupJson,$referencesJson,$translatabilityJson):array{
            $uuid=is_array($existing)?(string)$existing['uuid']:\Sabri\Localization\Infrastructure\Database::uuid();
            $secureId=null;$stored=$text;
            if(in_array($data,array('C4','C5'),true)||'private'===$risk){$secureId=$this->repo->storeSecurePayload('resource',$uuid,'source_text',$text);$stored='[ENCRYPTED RESTRICTED SOURCE]';}
            $placeholdersJson=wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if(!is_string($placeholdersJson)){throw new InvalidArgumentException('Resource placeholder evidence could not be encoded safely.');}
            $base=array('resource_key'=>$key,'source_locale'=>$locale,'source_text'=>$stored,'secure_payload_id'=>$secureId,'source_hash'=>$hash,'context'=>$context,'description'=>$description,'domain_name'=>$domain,'risk_class'=>$risk,'data_class'=>$data,'placeholders'=>$placeholdersJson,'markup_policy'=>$markupJson,'references_json'=>$referencesJson,'translatability_json'=>$translatabilityJson,'critical'=>RiskPolicy::criticalResource($risk,$domain)?1:0,'status'=>'active','updated_by'=>get_current_user_id());
            if(is_array($existing)){
                $version=(int)$input['row_version'];$base['source_version']=(int)$existing['source_version']+1;
                $updated=$this->repo->updateVersioned('resources',$uuid,$version,$base);
                if(!empty($existing['secure_payload_id'])&&(int)$existing['secure_payload_id']!==(int)$secureId){$this->repo->retireSecurePayload((int)$existing['secure_payload_id']);}
                $stale=$this->repo->markDependentUnitsStale($uuid,'source_changed');
                $links=DependencyInvalidator::markContentLinksStale($uuid);
                $bundles=DependencyInvalidator::invalidateActiveBundles($uuid);
                $this->audit->record('resource',$key,'resource_versioned','success',array('source_version'=>$base['source_version'],'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles,'hash'=>$hash));
                $this->outbox->enqueue('TranslatableResourceChanged','resource',$uuid,array('resource_key'=>$key,'source_version'=>$base['source_version'],'source_hash'=>$hash,'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles));
                if($stale>0||$links>0||$bundles>0){$this->outbox->enqueue('LocalizationCoverageDegraded','resource',$uuid,array('resource_key'=>$key,'reason'=>'source_changed','stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles));}
                return array('changed'=>true,'record'=>$updated,'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles);
            }
            $base['uuid']=$uuid;$base['source_version']=1;$base['created_by']=get_current_user_id();$base['row_version']=1;
            $created=$this->repo->insert('resources',$base);
            $this->audit->record('resource',$key,'resource_registered','success',array('source_version'=>1,'hash'=>$hash,'risk'=>$risk,'data_class'=>$data));
            return array('changed'=>true,'record'=>$created,'stale_units'=>0,'stale_content_links'=>0,'invalidated_bundles'=>0);
        });
        if(($result['stale_units']??0)>0||($result['stale_content_links']??0)>0||($result['invalidated_bundles']??0)>0){wp_cache_flush();}
        return $result;
    }

    public function retire(string $uuid,int $version,string $reason):array
    {
        $reason=trim($reason);
        if(''===$reason||strlen($reason)>1000){throw new InvalidArgumentException('A bounded retirement or rights-expiry reason is required.');}
        $resource=$this->repo->find('resources',$uuid)??throw new InvalidArgumentException('Translatable resource not found.');
        if('retired'===(string)$resource['status']){throw new InvalidArgumentException('Translatable resource is already retired.');}

        $result=$this->tx->run(function()use($resource,$uuid,$version,$reason):array{
            $updated=$this->repo->updateVersioned('resources',$uuid,$version,array('status'=>'retired','updated_by'=>get_current_user_id()));
            $stale=$this->repo->markDependentUnitsStale($uuid,'source_retired');
            $links=DependencyInvalidator::markContentLinksStale($uuid);
            $bundles=DependencyInvalidator::invalidateActiveBundles($uuid);
            $this->audit->record('resource',(string)$resource['resource_key'],'resource_retired','success',array('reason'=>$reason,'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles));
            $this->outbox->enqueue('TranslatableResourceChanged','resource',$uuid,array('resource_key'=>$resource['resource_key'],'source_version'=>(int)$resource['source_version'],'source_hash'=>$resource['source_hash'],'status'=>'retired','reason'=>$reason,'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles));
            $this->outbox->enqueue('LocalizationCoverageDegraded','resource',$uuid,array('resource_key'=>$resource['resource_key'],'reason'=>'source_retired','stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles));
            return array('record'=>$updated,'stale_units'=>$stale,'stale_content_links'=>$links,'invalidated_bundles'=>$bundles);
        });
        wp_cache_flush();
        return $result;
    }

    public function text(array $resource):string
    {
        if(!empty($resource['secure_payload_id'])){return $this->repo->readSecurePayload((int)$resource['secure_payload_id'],(string)$resource['uuid'],'source_text');}
        return (string)$resource['source_text'];
    }

    private function normalizeContextRefs(mixed $value): array
    {
        if(!is_array($value)||count($value)>100){throw new InvalidArgumentException('Resource context references must be a bounded array.');}
        $out=[];$seen=[];
        foreach($value as $row){
            if(!is_array($row)){throw new InvalidArgumentException('Resource context reference entries must be objects.');}
            $type=sanitize_key((string)($row['type']??''));
            $ref=trim((string)($row['ref']??''));
            if(!in_array($type,array('route','component','screenshot'),true)||''===$ref||strlen($ref)>191){
                throw new InvalidArgumentException('Resource context reference type/ref is invalid or oversized.');
            }
            if('route'===$type){
                if(!str_starts_with($ref,'/')||str_contains($ref,'://')||str_contains($ref,"\0")){
                    throw new InvalidArgumentException('Resource route context reference must be a same-origin relative route.');
                }
            }elseif(1!==preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/@-]{0,190}$/D',$ref)){
                throw new InvalidArgumentException('Resource component/screenshot context reference must be a stable canonical identifier.');
            }
            $key=$type.'|'.$ref;if(isset($seen[$key])){throw new InvalidArgumentException('Resource context reference is duplicated.');}
            $seen[$key]=true;$out[]=array('type'=>$type,'ref'=>$ref);
        }
        usort($out,static fn(array $a,array $b):int=>strcmp($a['type'].'|'.$a['ref'],$b['type'].'|'.$b['ref']));
        return $out;
    }

    private function encodeBoundedMetadata(array $value,string $label): string
    {
        $json=wp_json_encode($this->canonicalize($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!is_string($json)||strlen($json)>262144){throw new InvalidArgumentException('Resource '.$label.' is invalid or oversized.');}
        return $json;
    }

    private function canonicalize(mixed $value): mixed
    {
        if(!is_array($value)){return $value;}
        if(array_is_list($value)){return array_map(fn(mixed $item):mixed=>$this->canonicalize($item),$value);}
        ksort($value,SORT_STRING);
        foreach($value as $key=>$item){$value[$key]=$this->canonicalize($item);}
        return $value;
    }
}
