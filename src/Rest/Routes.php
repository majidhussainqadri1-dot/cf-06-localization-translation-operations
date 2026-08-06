<?php

declare(strict_types=1);

namespace Sabri\Localization\Rest;

use DomainException;
use InvalidArgumentException;
use Sabri\Localization\Contract\Manifest;
use Sabri\Localization\Plugin;
use Sabri\Localization\Security\Authorization;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Routes
{
    private const NS='sabri-localization/v1';
    public function __construct(private readonly array $s){}
    public function registerHooks():void{add_action('rest_api_init',[$this,'registerRoutes']);}
    public function registerRoutes():void
    {
        $this->route('/manifest',WP_REST_Server::READABLE,fn()=>Manifest::get(),'public');
        $this->route('/status',WP_REST_Server::READABLE,fn()=>$this->s['health']->report(),'audit');
        $this->route('/health',WP_REST_Server::READABLE,fn()=>$this->s['health']->report(),'manage');
        register_rest_route(self::NS,'/locales',[
            ['methods'=>WP_REST_Server::READABLE,'callback'=>function(){if(!Plugin::runtimeEnabled()&&!Authorization::allowed('manage')){return new WP_Error('slto_runtime_disabled','Localization runtime is not activated.',['status'=>503]);}return $this->ok(['items'=>$this->s['locale']->list(true)]);},'permission_callback'=>'__return_true'],
            ['methods'=>WP_REST_Server::CREATABLE,'callback'=>fn(WP_REST_Request $r)=>$this->mutate($r,'locales.create',fn()=>$this->s['locale']->register((array)$r->get_json_params()),201),'permission_callback'=>fn()=>Authorization::allowed('manage')],
        ]);
        $this->route('/locales/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'locales.transition',fn()=>$this->s['locale']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'release');
        $this->route('/resolve',WP_REST_Server::READABLE,function(WP_REST_Request $r){if(!Plugin::runtimeEnabled()&&!Authorization::allowed('manage')){return new WP_Error('slto_runtime_disabled','Localization runtime is not activated.',['status'=>503]);}return $this->s['locale']->resolve((string)$r->get_param('locale'));},'public');
        $this->route('/resources',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'resources.register',fn()=>$this->s['resource']->register((array)$r->get_json_params()),201),'manage');
        $this->route('/projects',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'projects.create',fn()=>$this->s['project']->create((array)$r->get_json_params()),201),'manage');
        $this->route('/projects/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'projects.transition',fn()=>$this->s['project']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'manage');
        $this->route('/assignments',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'assignments.create',fn()=>$this->s['project']->assign((array)$r->get_json_params()),201),'manage');
        $this->route('/assignments/(?P<uuid>[a-f0-9-]{36})/revoke',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'assignments.revoke',fn()=>$this->s['project']->revokeAssignment((string)$r['uuid'],(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'manage');
        $this->route('/queue',WP_REST_Server::READABLE,fn(WP_REST_Request $r)=>['items'=>$this->s['project']->queue(get_current_user_id(),sanitize_key((string)$r->get_param('role')),max(1,min(200,(int)($r->get_param('limit')?:100))))],'translate');
        $this->route('/units/(?P<uuid>[a-f0-9-]{36})/submit',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'units.submit',fn()=>$this->s['translation']->submit((string)$r['uuid'],(string)$r->get_param('target_text'),(int)$r->get_param('row_version'))),'translate');
        $this->route('/units/(?P<uuid>[a-f0-9-]{36})/review',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'units.review',fn()=>$this->s['translation']->review((string)$r['uuid'],(string)$r->get_param('decision'),(int)$r->get_param('row_version'),(string)$r->get_param('review_type'),(string)$r->get_param('reason'))),fn(WP_REST_Request $r):bool=>'domain'===(string)$r->get_param('review_type')?Authorization::allowed('review_domain'):Authorization::allowed('review_linguistic'));
        $this->route('/units/(?P<uuid>[a-f0-9-]{36})/comments',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'units.comment',fn()=>$this->s['translation']->comment((string)$r['uuid'],(string)$r->get_param('comment'),(string)($r->get_param('audience')?:'internal'),$r->get_param('parent_uuid')? (string)$r->get_param('parent_uuid'):null),201),fn()=>Authorization::allowed('translate')||Authorization::allowed('review_linguistic')||Authorization::allowed('review_domain'));
        $this->route('/terminology',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'terminology.create',fn()=>$this->s['terminology']->create((array)$r->get_json_params()),201),'terminology');
        $this->route('/terminology/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'terminology.transition',fn()=>$this->s['terminology']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'review_domain');
        $this->route('/style-guides',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'style-guides.create',fn()=>$this->s['terminology']->styleGuide((array)$r->get_json_params()),201),'terminology');
        $this->route('/style-guides/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'style-guides.transition',fn()=>$this->s['terminology']->transitionStyleGuide((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'review_domain');
        $this->route('/memory/suggest',WP_REST_Server::READABLE,fn(WP_REST_Request $r)=>['items'=>$this->s['terminology']->suggestMemory((string)$r->get_param('source'),(string)$r->get_param('source_locale'),(string)$r->get_param('target_locale'),sanitize_key((string)$r->get_param('domain')),max(1,min(50,(int)($r->get_param('limit')?:10))))],'translate');
        $this->route('/providers',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'providers.register',fn()=>$this->s['providers']->register((array)$r->get_json_params()),201),'provider');
        $this->route('/providers/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'providers.transition',fn()=>$this->s['providers']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'provider');
        $this->route('/mt/jobs/prepare',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'mt.prepare',fn()=>$this->s['mt']->prepare(is_array($r->get_param('unit_uuids'))?$r->get_param('unit_uuids'):[],(string)($r->get_param('purpose')?:'draft_translation'),(bool)$r->get_param('explicit_high_risk_approval')),201),fn(WP_REST_Request $r):bool=>Authorization::allowed('provider')&&(!(bool)$r->get_param('explicit_high_risk_approval')||Authorization::allowed('review_domain')));
        $this->route('/mt/jobs/(?P<uuid>[a-f0-9-]{36})/send',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'mt.send',fn()=>$this->s['mt']->send((string)$r['uuid'],is_array($r->get_param('payload'))?$r->get_param('payload'):[],(int)$r->get_param('row_version'))),'provider');
        $this->route('/mt/jobs/(?P<uuid>[a-f0-9-]{36})/review',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'mt.review',fn()=>$this->s['mt']->review((string)$r['uuid'],(string)$r->get_param('decision'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'review_domain');
        $this->route('/mt/jobs/(?P<uuid>[a-f0-9-]{36})/purge',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'mt.purge',fn()=>$this->s['mt']->purge((string)$r['uuid'],(int)$r->get_param('row_version'))),'provider');
        $this->route('/integrations/accept',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'integrations.accept',fn()=>$this->s['integrations']->accept((array)$r->get_json_params()),201),'release');
        $this->route('/integrations/(?P<uuid>[a-f0-9-]{36})/revoke',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'integrations.revoke',fn()=>$this->s['integrations']->revoke((string)$r['uuid'],(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'release');
        $this->route('/extraction/evidence',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'extraction.evidence',fn()=>$this->s['extraction']->record((array)$r->get_json_params()),201),'audit');
        $this->route('/qa/evidence',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'qa.evidence',fn()=>$this->s['qaEvidence']->record((array)$r->get_json_params()),201),'audit');
        $this->route('/bundles/(?P<uuid>[a-f0-9-]{36})/approvals',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.approval',fn()=>$this->s['releaseApprovals']->approve((string)$r['uuid'],(array)$r->get_json_params()),201),'release');
        $this->route('/bundles/build',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.build',fn()=>$this->s['bundle']->build((string)$r->get_param('locale')),201),'release');
        $this->route('/bundles/(?P<uuid>[a-f0-9-]{36})/qa',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.qa',fn()=>$this->s['bundle']->recordQa((string)$r['uuid'],(string)$r->get_param('rule'),(string)$r->get_param('result'),(string)$r->get_param('severity'),is_array($r->get_param('details'))?$r->get_param('details'):[]),201),'release');
        $this->route('/bundles/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.transition',fn()=>$this->s['bundle']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('reason'))),'release');
        $this->route('/bundles/(?P<uuid>[a-f0-9-]{36})/activate',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.activate',fn()=>$this->s['bundle']->activate((string)$r['uuid'],(int)$r->get_param('row_version'))),'release');
        $this->route('/bundles/(?P<uuid>[a-f0-9-]{36})/rollback',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'bundles.rollback',fn()=>$this->s['bundle']->rollback((string)$r['uuid'],(string)$r->get_param('target_uuid'),(int)$r->get_param('row_version'))),'release');
        $this->route('/bundles/(?P<locale>[A-Za-z0-9-]{2,35})/active',WP_REST_Server::READABLE,function(WP_REST_Request $r){if(!Plugin::runtimeEnabled()&&!Authorization::allowed('manage')){return new WP_Error('slto_runtime_disabled','Localization runtime is not activated.',['status'=>503]);}$bundle=$this->s['bundle']->publicBundle((string)$r['locale']);return null===$bundle?new WP_Error('slto_bundle_missing','No active locale bundle.',['status'=>404]):$bundle;},'public');
        $this->route('/feedback',WP_REST_Server::CREATABLE,function(WP_REST_Request $r){if(!Plugin::runtimeEnabled()){return new WP_Error('slto_runtime_disabled','Localization runtime is not activated.',['status'=>503]);}$bucket='feedback|'.(get_current_user_id()?:hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'')));if(!$this->s['repo']->rateLimit($bucket,10,3600)){return new WP_Error('slto_rate_limited','Too many translation feedback submissions.',['status'=>429]);}return $this->mutate($r,'feedback.submit',fn()=>$this->s['feedback']->submit((array)$r->get_json_params()),201,false);},'public');
        $this->route('/feedback/(?P<uuid>[a-f0-9-]{36})/transition',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'feedback.transition',fn()=>$this->s['feedback']->transition((string)$r['uuid'],(string)$r->get_param('to'),(int)$r->get_param('row_version'),(string)$r->get_param('outcome'))),'manage');
        $this->route('/content-links',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'content-links.register',fn()=>$this->s['contentLinks']->register((array)$r->get_json_params()),201),'manage');
        $this->route('/metrics',WP_REST_Server::READABLE,fn()=>$this->s['metrics']->summary(),'audit');
        $this->route('/migration/inventory',WP_REST_Server::READABLE,fn()=>$this->s['migration']->inventory(),'audit');
        $this->route('/migration/dry-run',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'migration.dry-run',fn()=>$this->s['migration']->dryRun(sanitize_key((string)$r->get_param('migration_key')),is_array($r->get_param('source'))?$r->get_param('source'):[])),'manage');
        $this->route('/runtime/activate',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'runtime.activate',function(){if(!defined('SLTO_FOUNDER_ACTIVATION_APPROVED')||true!==SLTO_FOUNDER_ACTIVATION_APPROVED){throw new InvalidArgumentException('Founder activation constant is not approved.');}$gate=$this->s['health']->activationEligible();if(!$gate['eligible']){throw new InvalidArgumentException('Localization activation gates are incomplete.');}if(false===update_option('slto_runtime_enabled',true,false)&&(bool)get_option('slto_runtime_enabled',false)!==true){throw new \RuntimeException('Localization runtime state could not be persisted.');}return ['runtime_enabled'=>true,'gates'=>$gate['gates']];}),'release');
        $this->route('/runtime/deactivate',WP_REST_Server::CREATABLE,fn(WP_REST_Request $r)=>$this->mutate($r,'runtime.deactivate',function(){if(false===update_option('slto_runtime_enabled',false,false)&&(bool)get_option('slto_runtime_enabled',true)!==false){throw new \RuntimeException('Localization runtime state could not be persisted.');}return ['runtime_enabled'=>false];}),'release');
    }

    private function route(string $path,string|array $methods,callable $callback,string|callable $permission):void
    {
        register_rest_route(self::NS,$path,['methods'=>$methods,'callback'=>function(WP_REST_Request $r)use($callback){try{$result=$callback($r);return $result instanceof WP_Error||$result instanceof WP_REST_Response?$result:$this->ok($result);}catch(Throwable $e){return $this->error($e);}},'permission_callback'=>$permission==='public'?'__return_true':(is_callable($permission)?$permission:fn()=>Authorization::allowed($permission))]);
    }

    private function mutate(WP_REST_Request $request,string $route,callable $operation,int $status=200,bool $requireKey=true):WP_REST_Response|WP_Error
    {
        $key=trim((string)$request->get_header('Idempotency-Key'));if($requireKey&&(''===$key||strlen($key)>191)){return new WP_Error('slto_idempotency_required','A valid Idempotency-Key header is required.',['status'=>400]);}if(''===$key){$key=hash('sha256',$route.'|'.wp_json_encode($request->get_json_params()).'|'.microtime(true));}
        $actor=get_current_user_id();$hash=hash('sha256',wp_json_encode($request->get_json_params(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$state=$this->s['repo']->storeIdempotency($actor,$route,$key,$hash);if(!$state['new']&&'completed'===$state['record']['status']){$body=json_decode((string)$state['record']['response_json'],true)?:[];return new WP_REST_Response($body,(int)$state['record']['response_code']);}if(!$state['new']){return new WP_Error('slto_request_in_progress','An identical operation is already processing.',['status'=>409]);}
        try{$result=$operation();$body=['data'=>$result,'trace_id'=>\Sabri\Localization\Infrastructure\Database::uuid()];$this->s['repo']->completeIdempotency($actor,$route,$key,$status,$body);return new WP_REST_Response($body,$status);}catch(Throwable $e){$this->s['repo']->failIdempotency($actor,$route,$key,sanitize_key(get_class($e)));throw $e;}
    }
    private function ok(mixed $data,int $status=200):WP_REST_Response{return new WP_REST_Response(['data'=>$data],$status);}
    private function error(Throwable $e):WP_Error{$trace=\Sabri\Localization\Infrastructure\Database::uuid();$code='slto_internal_error';$status=500;$message='Localization operation failed.';if($e instanceof InvalidArgumentException){$code='slto_invalid_request';$status=422;$message=$e->getMessage();}elseif($e instanceof DomainException&&'stale_version'===$e->getMessage()){$code='slto_stale_version';$status=409;$message='The record changed; reload before retrying.';}elseif($e instanceof DomainException&&'idempotency_conflict'===$e->getMessage()){$code='slto_idempotency_conflict';$status=409;$message='The idempotency key was reused with a different request.';}do_action('slto_safe_error',$code,$trace,$e);return new WP_Error($code,$message,['status'=>$status,'trace_id'=>$trace]);}
}
