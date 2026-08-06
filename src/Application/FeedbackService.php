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
        if(null===$locale||''===$category){throw new InvalidArgumentException('Feedback locale and category are required.');}
        $severity=in_array((string)($input['severity']??'normal'),array('low','normal','high','critical'),true)?(string)$input['severity']:'normal';
        $suggestion=Redactor::redact(sanitize_textarea_field((string)($input['suggestion']??'')))['text'];
        if(''===trim($suggestion)||strlen($suggestion)>10000){throw new InvalidArgumentException('Feedback is empty or exceeds the bounded limit.');}
        $route=(string)($input['route']??'');
        if(''!==$route&&(!str_starts_with($route,'/')||str_contains($route,'://'))){throw new InvalidArgumentException('Feedback route must be same-origin relative.');}
        return $this->tx->run(function() use ($input,$locale,$category,$severity,$suggestion,$route): array {
            $row=$this->repo->insert('feedback',array(
                'reporter_id'=>get_current_user_id(),'locale_tag'=>$locale,'resource_key'=>sanitize_text_field((string)($input['resource_key']??''))?:null,
                'route_path'=>$route?:null,'category'=>$category,'severity'=>$severity,'suggestion_text'=>$suggestion,'status'=>'reported',
                'assigned_to'=>null,'outcome_text'=>null,'row_version'=>1,
            ));
            $this->audit->record('feedback',(string)$row['uuid'],'translation_feedback_reported','success',array('locale'=>$locale,'category'=>$category,'severity'=>$severity));
            if('critical'===$severity){$this->outbox->enqueue('CriticalTranslationDefectDetected','feedback',(string)$row['uuid'],array('locale'=>$locale,'resource_key'=>$row['resource_key'],'category'=>$category));}
            return $row;
        });
    }

    public function transition(string $uuid,string $to,int $version,string $outcome=''): array
    {
        $row=$this->repo->find('feedback',$uuid)??throw new InvalidArgumentException('Translation feedback not found.');
        StateMachine::assert('defect',(string)$row['status'],$to);
        if(in_array($to,array('closed','released'),true)&&''===trim($outcome)){throw new InvalidArgumentException('A recorded outcome is required.');}
        return $this->tx->run(function() use ($row,$uuid,$to,$version,$outcome): array {
            $updated=$this->repo->updateVersioned('feedback',$uuid,$version,array('status'=>$to,'assigned_to'=>get_current_user_id(),'outcome_text'=>sanitize_textarea_field($outcome)));
            $this->audit->record('feedback',$uuid,'translation_feedback_transition','success',array('from'=>$row['status'],'to'=>$to));
            if('released'===$to){$this->outbox->enqueue('TranslationCorrectionReleased','feedback',$uuid,array('locale'=>$row['locale_tag'],'resource_key'=>$row['resource_key']));}
            return $updated;
        });
    }
}
