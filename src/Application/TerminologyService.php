<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Database;
use Sabri\Localization\Infrastructure\DependencyInvalidator;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class TerminologyService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly AuditRepository $audit,private readonly Outbox $outbox,private readonly Transaction $tx){}

    public function create(array $input): array
    {
        $concept=sanitize_key((string)($input['concept_id']??''));$domain=sanitize_key((string)($input['domain']??''));
        $source=trim((string)($input['source_term']??''));$approved=trim((string)($input['approved_term']??''));
        $sourceLocale=LocaleValidator::canonicalize((string)($input['source_locale']??''));$targetLocale=LocaleValidator::canonicalize((string)($input['target_locale']??''));
        if(''===$concept||strlen($concept)>80||''===$domain||strlen($domain)>80||''===$source||strlen($source)>191||''===$approved||strlen($approved)>191||null===$sourceLocale||null===$targetLocale||$sourceLocale===$targetLocale){throw new InvalidArgumentException('Terminology entry is incomplete, same-locale, or exceeds canonical storage bounds.');}
        foreach(array($sourceLocale,$targetLocale) as $localeTag){
            if(!is_array($this->repo->findOne('locales','locale_tag',$localeTag))){
                throw new InvalidArgumentException('Terminology locale is not registered: '.$localeTag);
            }
        }
        $prohibited=[];foreach(is_array($input['prohibited_terms']??null)?$input['prohibited_terms']:[] as $term){$term=trim((string)$term);if(''!==$term){if(strlen($term)>191){throw new InvalidArgumentException('A prohibited terminology variant exceeds the bounded term length.');}$prohibited[]=$term;}}
        $prohibited=array_values(array_unique($prohibited));if(count($prohibited)>500){throw new InvalidArgumentException('Too many prohibited terminology variants.');}
        $prohibitedJson=wp_json_encode($prohibited,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($prohibitedJson)||strlen($prohibitedJson)>131072){throw new InvalidArgumentException('Prohibited terminology evidence is invalid or oversized.');}
        $definition=sanitize_textarea_field((string)($input['definition']??''));$context=sanitize_textarea_field((string)($input['context']??''));$grammar=sanitize_textarea_field((string)($input['grammar_notes']??''));
        if(strlen($definition)>20000||strlen($context)>20000||strlen($grammar)>20000){throw new InvalidArgumentException('Terminology descriptive evidence exceeds the bounded limit.');}
        return $this->tx->run(function()use($concept,$domain,$source,$approved,$sourceLocale,$targetLocale,$prohibitedJson,$definition,$context,$grammar):array{
            $existing=$this->repo->list('terminology',array('concept_id'=>$concept,'target_locale'=>$targetLocale),1,0,'term_version DESC');
            $termVersion=empty($existing)?1:(int)$existing[0]['term_version']+1;
            $row=$this->repo->insert('terminology',array(
                'concept_id'=>$concept,'domain_name'=>$domain,'source_locale'=>$sourceLocale,'source_term'=>$source,'target_locale'=>$targetLocale,'approved_term'=>$approved,
                'prohibited_terms'=>$prohibitedJson,'definition_text'=>$definition,'context_text'=>$context,'grammar_notes'=>$grammar,
                'created_by'=>get_current_user_id(),'status'=>'proposed','term_version'=>$termVersion,'row_version'=>1,
            ));
            $this->audit->record('terminology',(string)$row['uuid'],'terminology_proposed','success',array('concept_id'=>$concept,'locale'=>$targetLocale,'domain'=>$domain,'term_version'=>$termVersion));
            return $row;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $reason=''): array
    {
        $row=$this->repo->find('terminology',$uuid)??throw new InvalidArgumentException('Terminology entry not found.');StateMachine::assert('terminology',(string)$row['status'],$to);
        $reason=sanitize_textarea_field($reason);if(strlen($reason)>2000){throw new InvalidArgumentException('Terminology transition reason exceeds the bounded limit.');}
        if('approved'===$to&&(int)$row['created_by']===get_current_user_id()){throw new InvalidArgumentException('Terminology proposer cannot approve their own entry.');}
        if('active'===$to&&(int)($row['reviewer_id']??0)<=0){throw new InvalidArgumentException('Terminology activation requires preserved approval provenance.');}
        $result=$this->tx->run(function()use($row,$to,$version,$reason):array{
            $changes=array('status'=>$to);
            if('approved'===$to){$changes['reviewer_id']=get_current_user_id();}
            if('active'===$to){$changes['effective_at']=Database::now();}
            $updated=$this->repo->updateVersioned('terminology',(string)$row['uuid'],$version,$changes);
            $propagation=array('resources'=>0,'stale_units'=>0,'stale_content_links'=>0,'invalidated_bundles'=>0);
            if('active'===$to||'active'===(string)$row['status']){
                $propagation=DependencyInvalidator::invalidateLocaleDomain((string)$row['target_locale'],(string)$row['domain_name'],'terminology_policy_changed');
            }
            $this->audit->record('terminology',(string)$row['uuid'],'terminology_transition','success',array('from'=>$row['status'],'to'=>$to,'reason'=>$reason,'approval_reviewer_id'=>$row['reviewer_id']??($changes['reviewer_id']??null),'propagation'=>$propagation));
            if('active'===$to){$this->outbox->enqueue('TerminologyEntryApproved','terminology',(string)$row['uuid'],array('concept_id'=>$row['concept_id'],'locale'=>$row['target_locale'],'domain'=>$row['domain_name']));}
            if('deprecated'===$to){$this->outbox->enqueue('TerminologyEntryDeprecated','terminology',(string)$row['uuid'],array('concept_id'=>$row['concept_id'],'locale'=>$row['target_locale']));}
            if(($propagation['stale_units']??0)>0||($propagation['stale_content_links']??0)>0||($propagation['invalidated_bundles']??0)>0){
                $this->outbox->enqueue('LocalizationPolicyChanged','terminology',(string)$row['uuid'],array('locale'=>$row['target_locale'],'domain'=>$row['domain_name'],'reason'=>'terminology_policy_changed','propagation'=>$propagation));
            }
            return array('record'=>$updated,'propagation'=>$propagation);
        });
        if(array_sum(array_map('intval',(array)($result['propagation']??array())))>0){wp_cache_flush();}
        return $result['record'];
    }

    public function styleGuide(array $input): array
    {
        $locale=LocaleValidator::canonicalize((string)($input['locale']??''));if(null===$locale||!$this->repo->findOne('locales','locale_tag',$locale)){throw new InvalidArgumentException('Style guide locale is invalid or unregistered.');}
        $domain=sanitize_key((string)($input['domain']??'platform'))?:'platform';if(strlen($domain)>80){throw new InvalidArgumentException('Style guide domain exceeds the canonical storage bound.');}
        $rules=is_array($input['rules']??null)?$input['rules']:array();if(empty($rules)){throw new InvalidArgumentException('Style guide rules are required.');}
        $encoded=wp_json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(false===$encoded||strlen($encoded)>250000){throw new InvalidArgumentException('Style guide rules exceed the bounded limit.');}
        $rawExamples=is_array($input['examples']??null)?$input['examples']:array();if(count($rawExamples)>100){throw new InvalidArgumentException('Style guide examples exceed the bounded item limit.');}
        $examples=wp_json_encode($rawExamples,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($examples)||strlen($examples)>250000){throw new InvalidArgumentException('Style guide examples exceed the bounded limit.');}
        return $this->tx->run(function()use($locale,$domain,$encoded,$examples):array{
            $existing=$this->repo->list('style_guides',array('locale_tag'=>$locale,'domain_name'=>$domain),1,0,'guide_version DESC');$guideVersion=empty($existing)?1:(int)$existing[0]['guide_version']+1;
            $row=$this->repo->insert('style_guides',array('locale_tag'=>$locale,'domain_name'=>$domain,'guide_version'=>$guideVersion,'rules_json'=>$encoded,'examples_json'=>$examples,'created_by'=>get_current_user_id(),'approved_by'=>null,'status'=>'draft','effective_at'=>null,'row_version'=>1));
            $this->audit->record('style_guide',(string)$row['uuid'],'style_guide_drafted','success',array('locale'=>$locale,'domain'=>$domain,'guide_version'=>$guideVersion));
            return $row;
        });
    }

    public function transitionStyleGuide(string $uuid,string $to,int $version,string $reason=''): array
    {
        $row=$this->repo->find('style_guides',$uuid)??throw new InvalidArgumentException('Style guide not found.');$map=['draft'=>['approved','deprecated'],'approved'=>['active','deprecated'],'active'=>['deprecated'],'deprecated'=>[]];
        if(!in_array($to,$map[(string)$row['status']]??[],true)){throw new InvalidArgumentException('Invalid style guide transition.');}
        $reason=sanitize_textarea_field($reason);if(strlen($reason)>2000){throw new InvalidArgumentException('Style guide transition reason exceeds the bounded limit.');}
        if('approved'===$to&&(int)$row['created_by']===get_current_user_id()){throw new InvalidArgumentException('Style guide author cannot approve their own guide.');}
        if('active'===$to&&(int)($row['approved_by']??0)<=0){throw new InvalidArgumentException('Style guide activation requires preserved approval provenance.');}
        $result=$this->tx->run(function()use($row,$to,$version,$reason):array{
            $changes=array('status'=>$to);
            if('approved'===$to){$changes['approved_by']=get_current_user_id();}
            if('active'===$to){$changes['effective_at']=Database::now();}
            $updated=$this->repo->updateVersioned('style_guides',(string)$row['uuid'],$version,$changes);
            $propagation=array('resources'=>0,'stale_units'=>0,'stale_content_links'=>0,'invalidated_bundles'=>0);
            if('active'===$to||'active'===(string)$row['status']){
                $propagation=DependencyInvalidator::invalidateLocaleDomain((string)$row['locale_tag'],(string)$row['domain_name'],'style_policy_changed');
            }
            $this->audit->record('style_guide',(string)$row['uuid'],'style_guide_transition','success',array('from'=>$row['status'],'to'=>$to,'reason'=>$reason,'approval_actor_id'=>$row['approved_by']??($changes['approved_by']??null),'propagation'=>$propagation));
            if(($propagation['stale_units']??0)>0||($propagation['stale_content_links']??0)>0||($propagation['invalidated_bundles']??0)>0){
                $this->outbox->enqueue('LocalizationPolicyChanged','style_guide',(string)$row['uuid'],array('locale'=>$row['locale_tag'],'domain'=>$row['domain_name'],'reason'=>'style_policy_changed','propagation'=>$propagation));
            }
            return array('record'=>$updated,'propagation'=>$propagation);
        });
        if(array_sum(array_map('intval',(array)($result['propagation']??array())))>0){wp_cache_flush();}
        return $result['record'];
    }

    public function suggestMemory(string $source,string $sourceLocale,string $targetLocale,string $domain,string $context,int $limit=10): array
    {
        $sourceLocale=LocaleValidator::canonicalize($sourceLocale)??'';$targetLocale=LocaleValidator::canonicalize($targetLocale)??'';$domain=sanitize_key($domain);$context=trim($context);$source=trim($source);
        if(''===$sourceLocale||''===$targetLocale||''===$domain||''===$source||''===$context){throw new InvalidArgumentException('Translation memory query requires source, locale pair, domain and context.');}
        if(strlen($source)>4000||strlen($context)>16000){throw new InvalidArgumentException('Translation memory similarity query exceeds the bounded computational limit.');}
        $contextHash=hash('sha256',$context);
        $rows=$this->repo->list('memory',array('source_locale'=>$sourceLocale,'target_locale'=>$targetLocale,'domain_name'=>$domain,'status'=>'approved'),200);$lower=static fn(string $value):string=>function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);$suggestions=[];$sourceLength=max(1,strlen($source));
        foreach($rows as $row){
            $candidate=(string)$row['source_segment'];$candidateLength=max(1,strlen($candidate));$ratio=min($sourceLength,$candidateLength)/max($sourceLength,$candidateLength);if($ratio<0.35||$candidateLength>8000){continue;}
            similar_text($lower($source),$lower($candidate),$score);if($score<55.0){continue;}
            $contextMatch=hash_equals((string)$row['context_hash'],$contextHash);$provenance=json_decode((string)$row['provenance_json'],true);if(!is_array($provenance)){$provenance=[];}
            $suggestions[]=array(
                'uuid'=>$row['uuid'],'source'=>$row['source_segment'],'target'=>$row['target_segment'],'score'=>round($score,2),'risk'=>$row['risk_class'],
                'license_code'=>$row['license_code'],'context_match'=>$contextMatch,'warning'=>$contextMatch?'fuzzy-suggestion-human-review-required':'context-mismatch-human-review-required',
                'auto_accept'=>false,'provenance'=>$provenance,
            );
        }
        usort($suggestions,static function(array $a,array $b):int{$context=((int)$b['context_match'])<=>((int)$a['context_match']);return 0!==$context?$context:$b['score']<=>$a['score'];});return array_slice($suggestions,0,max(1,min(50,$limit)));
    }
}
