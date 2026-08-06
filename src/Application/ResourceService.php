<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\BidiValidator;
use Sabri\Localization\Domain\Translation\PlaceholderValidator;
use Sabri\Localization\Domain\Translation\RiskPolicy;
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
        if(1!==preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+){1,15}$/D',$key)){throw new InvalidArgumentException('Resource key must be stable, semantic and namespaced.');}
        $locale=LocaleValidator::canonicalize((string)($input['source_locale']??''));
        if(null===$locale||!$this->repo->findOne('locales','locale_tag',$locale)){throw new InvalidArgumentException('Source locale is not registered.');}
        $text=(string)($input['source_text']??'');if(''===trim($text)||strlen($text)>500000){throw new InvalidArgumentException('Source text is empty or exceeds the bounded limit.');}
        BidiValidator::assertSafe($text);
        $risk=strtolower((string)($input['risk_class']??'low'));$data=strtoupper((string)($input['data_class']??'C1'));
        if(!in_array($risk,array('low','medium','high','critical','private'),true)||!in_array($data,array('C1','C2','C3','C4','C5'),true)){throw new InvalidArgumentException('Invalid resource risk or data class.');}
        $domain=sanitize_key((string)($input['domain']??'platform'))?:'platform';
        $schema=PlaceholderValidator::normalizeSchema($input['placeholders']??array());PlaceholderValidator::assertSource($text,$schema);
        $existing=$this->repo->findOne('resources','resource_key',$key);
        $hash=hash('sha256',wp_json_encode(array('key'=>$key,'locale'=>$locale,'text'=>$text,'context'=>(string)($input['context']??''),'domain'=>$domain,'risk'=>$risk,'data'=>$data,'placeholders'=>$schema),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        if(is_array($existing)&&hash_equals((string)$existing['source_hash'],$hash)){return array('changed'=>false,'record'=>$existing);}
        return $this->tx->run(function()use($existing,$key,$locale,$text,$hash,$input,$domain,$risk,$data,$schema):array{
            $uuid=is_array($existing)?(string)$existing['uuid']:\Sabri\Localization\Infrastructure\Database::uuid();
            $secureId=null;$stored=$text;
            if(in_array($data,array('C4','C5'),true)||'private'===$risk){$secureId=$this->repo->storeSecurePayload('resource',$uuid,'source_text',$text);$stored='[ENCRYPTED RESTRICTED SOURCE]';}
            $base=array('resource_key'=>$key,'source_locale'=>$locale,'source_text'=>$stored,'secure_payload_id'=>$secureId,'source_hash'=>$hash,'context'=>wp_kses_post((string)($input['context']??'')),'description'=>sanitize_textarea_field((string)($input['description']??'')),'domain_name'=>$domain,'risk_class'=>$risk,'data_class'=>$data,'placeholders'=>wp_json_encode($schema),'markup_policy'=>wp_json_encode($input['markup_policy']??array()),'references_json'=>wp_json_encode(array_slice(is_array($input['references']??null)?$input['references']:array(),0,100)),'translatability_json'=>wp_json_encode($input['translatability']??array()),'critical'=>RiskPolicy::criticalResource($risk,$domain)?1:0,'status'=>'active','updated_by'=>get_current_user_id());
            if(is_array($existing)){$version=(int)$existing['row_version'];$base['source_version']=(int)$existing['source_version']+1;$updated=$this->repo->updateVersioned('resources',$uuid,$version,$base);if(!empty($existing['secure_payload_id'])&&(int)$existing['secure_payload_id']!==(int)$secureId){$this->repo->retireSecurePayload((int)$existing['secure_payload_id']);}$stale=$this->repo->markDependentUnitsStale($uuid,'source_changed');$this->audit->record('resource',$key,'resource_versioned','success',array('source_version'=>$base['source_version'],'stale_units'=>$stale,'hash'=>$hash));$this->outbox->enqueue('TranslatableResourceChanged','resource',$uuid,array('resource_key'=>$key,'source_version'=>$base['source_version'],'source_hash'=>$hash,'stale_units'=>$stale));return array('changed'=>true,'record'=>$updated,'stale_units'=>$stale);}
            $base['uuid']=$uuid;$base['source_version']=1;$base['created_by']=get_current_user_id();$base['row_version']=1;$created=$this->repo->insert('resources',$base);$this->audit->record('resource',$key,'resource_registered','success',array('source_version'=>1,'hash'=>$hash,'risk'=>$risk,'data_class'=>$data));return array('changed'=>true,'record'=>$created,'stale_units'=>0);
        });
    }

    public function text(array $resource):string
    {
        if(!empty($resource['secure_payload_id'])){return $this->repo->readSecurePayload((int)$resource['secure_payload_id'],(string)$resource['uuid'],'source_text');}
        return (string)$resource['source_text'];
    }
}
