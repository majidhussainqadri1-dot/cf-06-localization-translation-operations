<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use Sabri\Localization\Domain\Translation\BidiValidator;
use Sabri\Localization\Domain\Translation\MarkupValidator;
use Sabri\Localization\Domain\Translation\NumberUnitGuard;
use Sabri\Localization\Domain\Translation\PlaceholderValidator;
use Sabri\Localization\Domain\Translation\RiskPolicy;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;

final class QaService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly ResourceService $resources){}

    public function unit(array $unit,array $resource,string $target):array
    {
        $checks=[];$schema=json_decode((string)$resource['placeholders'],true)?:array();
        $checks[]=$this->check('placeholders',fn()=>PlaceholderValidator::assertTarget($this->resources->text($resource),$target,$schema),'critical');
        $checks[]=$this->check('markup',fn()=>MarkupValidator::assertEquivalent($this->resources->text($resource),$target),'high');
        $checks[]=$this->check('bidi',fn()=>BidiValidator::assertSafe($target),'high');
        $checks[]=$this->check('numbers_units',fn()=>NumberUnitGuard::assertImmutable($this->resources->text($resource),$target,RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])),'critical');
        $checks[]=$this->terminology($resource,$unit,$target);
        foreach($checks as $check){$this->repo->insert('qa_results',array('target_type'=>'unit','target_uuid'=>$unit['uuid'],'rule_code'=>$check['rule'],'result'=>$check['result'],'severity'=>$check['severity'],'details_json'=>wp_json_encode($check['details']),'reviewer_id'=>get_current_user_id(),'fixed_at'=>null));}
        $passed=!in_array('fail',array_column($checks,'result'),true);
        return array('passed'=>$passed,'checks'=>$checks);
    }

    public function bundle(string $locale,array $items,array $coverage):array
    {
        $checks=[
            ['rule'=>'bundle_nonempty','result'=>empty($items)?'fail':'pass','severity'=>'critical','details'=>['count'=>count($items)]],
            ['rule'=>'critical_coverage','result'=>(float)$coverage['critical_coverage']>=100.0?'pass':'fail','severity'=>'critical','details'=>$coverage],
            ['rule'=>'unique_keys','result'=>count(array_unique(array_keys($items)))===count($items)?'pass':'fail','severity'=>'high','details'=>[]],
        ];
        return array('passed'=>!in_array('fail',array_column($checks,'result'),true),'checks'=>$checks);
    }

    private function check(string $rule,callable $callback,string $severity):array
    {
        try{$callback();return ['rule'=>$rule,'result'=>'pass','severity'=>$severity,'details'=>[]];}catch(\Throwable $e){return ['rule'=>$rule,'result'=>'fail','severity'=>$severity,'details'=>['code'=>sanitize_key($e->getMessage()),'message'=>'Validation failed.']];}
    }

    private function terminology(array $resource,array $unit,string $target):array
    {
        $rows=$this->repo->list('terminology',array('target_locale'=>$unit['target_locale'],'domain_name'=>$resource['domain_name'],'status'=>'active'),500);
        $violations=[];
        foreach($rows as $row){$prohibited=json_decode((string)$row['prohibited_terms'],true)?:array();foreach($prohibited as $term){if(''!==$term&&false!==(function_exists('mb_stripos')?mb_stripos($target,(string)$term,0,'UTF-8'):stripos($target,(string)$term))){$violations[]=(string)$term;}}}
        return ['rule'=>'terminology','result'=>empty($violations)?'pass':'fail','severity'=>RiskPolicy::requiresDomainReview((string)$resource['risk_class'],(string)$resource['domain_name'])?'critical':'medium','details'=>['prohibited_count'=>count($violations)]];
    }
}
