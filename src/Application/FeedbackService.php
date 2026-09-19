<?php

declare(strict_types=1);

namespace Sabri\Localization\Application;

use InvalidArgumentException;
use Sabri\Localization\Domain\Locale\LocaleValidator;
use Sabri\Localization\Domain\Translation\Redactor;
use Sabri\Localization\Domain\Workflow\StateMachine;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;

final class FeedbackService
{
    public function __construct(
        private readonly LocalizationRepository $repo,
        private readonly AuditRepository $audit,
        private readonly Outbox $outbox,
        private readonly Transaction $tx
    ) {}

    public function submit(array $input): array
    {
        $locale=LocaleValidator::canonicalize((string)($input['locale']??''));
        $category=sanitize_key((string)($input['category']??''));
        if(null===$locale||''===$category||strlen($category)>40){throw new InvalidArgumentException('Feedback locale and bounded category are required.');}
        $localeRow=$this->repo->findOne('locales','locale_tag',$locale);
        if(!is_array($localeRow)||!in_array((string)$localeRow['status'],array('enabled','degraded'),true)){
            throw new InvalidArgumentException('Feedback locale is not currently available on a public localization surface.');
        }
        $reportedSeverity=in_array((string)($input['severity']??'normal'),array('low','normal','high','critical'),true)?(string)$input['severity']:'normal';
        $suggestion=Redactor::redact(sanitize_textarea_field((string)($input['suggestion']??'')))['text'];
        if(''===trim($suggestion)||strlen($suggestion)>10000){throw new InvalidArgumentException('Feedback is empty or exceeds the bounded limit.');}
        $route=trim((string)($input['route']??''));
        if(strlen($route)>255||(''!==$route&&(!str_starts_with($route,'/')||str_contains($route,'://')||str_contains($route,"\0")))){throw new InvalidArgumentException('Feedback route must be a bounded same-origin relative path.');}
        $resourceKey=trim((string)($input['resource_key']??''));
        if(strlen($resourceKey)>191){throw new InvalidArgumentException('Feedback resource key exceeds the bounded limit.');}
        if(''===$resourceKey&&''===$route){throw new InvalidArgumentException('Feedback must identify a resource key or route.');}
        return $this->tx->run(function() use ($locale,$category,$reportedSeverity,$suggestion,$route,$resourceKey): array {
            $row=$this->repo->insert('feedback',array(
                'reporter_id'=>get_current_user_id(),'locale_tag'=>$locale,'resource_key'=>''===$resourceKey?null:sanitize_text_field($resourceKey),
                'route_path'=>$route?:null,'category'=>$category,'severity'=>$reportedSeverity,'suggestion_text'=>$suggestion,'status'=>'reported',
                'assigned_to'=>null,'outcome_text'=>null,'row_version'=>1,
            ));
            $this->audit->record('feedback',(string)$row['uuid'],'translation_feedback_reported','success',array('locale'=>$locale,'category'=>$category,'reported_severity'=>$reportedSeverity));
            return $row;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $outcome=''): array
    {
        $row=$this->repo->find('feedback',$uuid)??throw new InvalidArgumentException('Translation feedback not found.');
        $outcome=sanitize_textarea_field($outcome);if(strlen($outcome)>10000){throw new InvalidArgumentException('Feedback outcome exceeds the bounded limit.');}
        StateMachine::assert('defect',(string)$row['status'],$to);
        if(in_array($to,array('closed','released'),true)&&''===trim($outcome)){throw new InvalidArgumentException('A recorded outcome is required.');}
        return $this->tx->run(function() use ($row,$uuid,$to,$version,$outcome): array {
            $updated=$this->repo->updateVersioned('feedback',$uuid,$version,array('status'=>$to,'assigned_to'=>get_current_user_id(),'outcome_text'=>$outcome));
            $this->audit->record('feedback',$uuid,'translation_feedback_transition','success',array('from'=>$row['status'],'to'=>$to,'reported_severity'=>$row['severity']));
            if('triaged'===$to&&'critical'===(string)$row['severity']){$this->outbox->enqueue('CriticalTranslationDefectDetected','feedback',$uuid,array('locale'=>$row['locale_tag'],'resource_key'=>$row['resource_key'],'category'=>$row['category'],'triaged_by'=>get_current_user_id()));}
            if('closed'===(string)$row['status']&&'triaged'===$to){$this->outbox->enqueue('TranslationFeedbackReopened','feedback',$uuid,array('locale'=>$row['locale_tag'],'resource_key'=>$row['resource_key'],'category'=>$row['category']));}
            if('released'===$to){$this->outbox->enqueue('TranslationCorrectionReleased','feedback',$uuid,array('locale'=>$row['locale_tag'],'resource_key'=>$row['resource_key']));}
            return $updated;
        });
    }
}
