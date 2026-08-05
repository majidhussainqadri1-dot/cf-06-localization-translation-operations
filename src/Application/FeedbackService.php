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

final class FeedbackService
{
    public function __construct(private readonly LocalizationRepository $repo,private readonly AuditRepository $audit,private readonly Outbox $outbox){}

    public function submit(array $input):array
    {
        $locale=LocaleValidator::canonicalize((string)($input['locale']??''));if(null===$locale){throw new InvalidArgumentException('Feedback locale is invalid.');}$category=sanitize_key((string)($input['category']??'general'));$severity=in_array((string)($input['severity']??'normal'),array('low','normal','high','critical'),true)?(string)$input['severity']:'normal';$suggestion=Redactor::redact(sanitize_textarea_field((string)($input['suggestion']??'')))['text'];if(strlen($suggestion)>10000){throw new InvalidArgumentException('Feedback exceeds the bounded limit.');}$route=(string)($input['route']??'');if(''!==$route&&(!str_starts_with($route,'/')||str_contains($route,'://'))){throw new InvalidArgumentException('Feedback route must be same-origin relative.');}
        $row=$this->repo->insert('feedback',array('reporter_id'=>get_current_user_id(),'locale_tag'=>$locale,'resource_key'=>sanitize_text_field((string)($input['resource_key']??''))?:null,'route_path'=>$route?:null,'category'=>$category,'severity'=>$severity,'suggestion_text'=>$suggestion,'status'=>'reported','assigned_to'=>null,'outcome_text'=>null,'row_version'=>1));$this->audit->record('feedback',(string)$row['uuid'],'translation_feedback_reported','success',array('locale'=>$locale,'category'=>$category,'severity'=>$severity));if('critical'===$severity){$this->outbox->enqueue('CriticalTranslationDefectDetected','feedback',(string)$row['uuid'],array('locale'=>$locale,'resource_key'=>$row['resource_key'],'category'=>$category));}return $row;
    }

    public function transition(string $uuid,string $to,int $version,string $outcome=''):array
    {
        $row=$this->repo->find('feedback',$uuid)??throw new InvalidArgumentException('Translation feedback not found.');StateMachine::assert('defect',(string)$row['status'],$to);$updated=$this->repo->updateVersioned('feedback',$uuid,$version,['status'=>$to,'assigned_to'=>get_current_user_id(),'outcome_text'=>sanitize_textarea_field($outcome)]);$this->audit->record('feedback',$uuid,'translation_feedback_transition','success',['from'=>$row['status'],'to'=>$to]);if('released'===$to){$this->outbox->enqueue('TranslationCorrectionReleased','feedback',$uuid,['locale'=>$row['locale_tag'],'resource_key'=>$row['resource_key']]);}return $updated;
    }
}
