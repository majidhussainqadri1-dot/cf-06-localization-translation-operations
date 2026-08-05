<?php

declare(strict_types=1);

namespace Sabri\Localization\Admin;

use Sabri\Localization\Security\Authorization;
use Throwable;

final class AdminPage
{
    public function __construct(private readonly array $s){}
    public function registerHooks():void
    {
        add_action('admin_menu',[$this,'menu']);add_action('admin_enqueue_scripts',[$this,'assets']);add_action('admin_post_slto_admin_action',[$this,'handle']);
    }
    public function menu():void
    {
        add_menu_page('Localization Operations','Localization','manage_sabri_localization','sabri-localization',[$this,'render'],'dashicons-translation',58);
    }
    public function assets(string $hook):void
    {
        if('toplevel_page_sabri-localization'!==$hook){return;}wp_enqueue_style('slto-admin',SABRI_SLTO_URL.'assets/admin.css',[],SABRI_SLTO_VERSION);
    }
    public function handle():void
    {
        if(!Authorization::allowed('manage')){wp_die(esc_html__('You are not allowed to manage localization operations.','sabri-localization-translation-operations'));}check_admin_referer('slto_admin_action');$action=sanitize_key((string)($_POST['slto_action']??''));
        try{
            $result=match($action){
                'build_bundle'=>$this->s['bundle']->build(sanitize_text_field((string)($_POST['locale']??''))),
                'deactivate_runtime'=>$this->deactivateRuntime(),
                'process_jobs'=>$this->s['jobs']->run(100),
                'dispatch_events'=>['dispatched'=>$this->s['outbox']->dispatch(100)],
                default=>throw new \InvalidArgumentException('Unknown localization administrator action.'),
            };
            set_transient('slto_admin_notice_'.get_current_user_id(),['type'=>'success','message'=>'Localization action completed.','details'=>$result],60);
        }catch(Throwable $e){set_transient('slto_admin_notice_'.get_current_user_id(),['type'=>'error','message'=>$e->getMessage()],60);}
        wp_safe_redirect(add_query_arg(['page'=>'sabri-localization','tab'=>sanitize_key((string)($_POST['return_tab']??'overview'))],admin_url('admin.php')));exit;
    }
    private function deactivateRuntime():array{update_option('slto_runtime_enabled',false,false);return ['runtime_enabled'=>false];}
    public function render():void
    {
        if(!Authorization::allowed('manage')){wp_die(esc_html__('You are not allowed to view localization operations.','sabri-localization-translation-operations'));}
        $tab=sanitize_key((string)($_GET['tab']??'overview'));$allowed=['overview','locales','projects','units','terminology','releases','providers','feedback','health'];if(!in_array($tab,$allowed,true)){$tab='overview';}
        $notice=get_transient('slto_admin_notice_'.get_current_user_id());delete_transient('slto_admin_notice_'.get_current_user_id());
        echo '<div class="wrap slto-wrap" dir="auto"><h1><span class="dashicons dashicons-translation" aria-hidden="true"></span> '.esc_html__('Localization and Translation Operations','sabri-localization-translation-operations').'</h1>';
        echo '<p class="slto-truth"><strong>'.esc_html__('Truth status:','sabri-localization-translation-operations').'</strong> '.esc_html__('Source-code candidate; staging, live and operational acceptance remain pending.','sabri-localization-translation-operations').'</p>';
        if(is_array($notice)){echo '<div class="notice notice-'.esc_attr($notice['type']).' is-dismissible"><p>'.esc_html($notice['message']).'</p></div>';}
        echo '<nav class="nav-tab-wrapper" aria-label="'.esc_attr__('Localization sections','sabri-localization-translation-operations').'">';foreach($allowed as $item){echo '<a class="nav-tab '.($tab===$item?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(['page'=>'sabri-localization','tab'=>$item],admin_url('admin.php'))).'">'.esc_html(ucwords(str_replace('_',' ',$item))).'</a>';}echo '</nav>';
        match($tab){'locales'=>$this->table('Locales',$this->s['repo']->list('locales',[],200,0,'id ASC'),['locale_tag','direction','fallback_tag','status','row_version','updated_at']),'projects'=>$this->table('Translation Projects',$this->s['repo']->list('projects',[],200),['name','source_locale','target_locales','priority','status','due_at','updated_at']),'units'=>$this->table('Translation Units',$this->s['repo']->list('units',[],200),['uuid','target_locale','status','machine_draft','qa_status','stale_reason','updated_at']),'terminology'=>$this->table('Terminology',$this->s['repo']->list('terminology',[],200),['concept_id','domain_name','source_term','target_locale','approved_term','status','term_version']),'releases'=>$this->releases(),'providers'=>$this->table('Providers',$this->s['repo']->list('providers',[],200),['provider_key','provider_type','region_code','retention_days','training_allowed','status','contract_version']),'feedback'=>$this->table('Translation Feedback',$this->s['repo']->list('feedback',[],200),['locale_tag','resource_key','category','severity','status','assigned_to','updated_at']),'health'=>$this->health(),default=>$this->overview()};
        echo '</div>';
    }
    private function overview():void
    {
        $health=$this->s['health']->report();echo '<section class="slto-grid" aria-label="'.esc_attr__('Localization overview','sabri-localization-translation-operations').'">';foreach(['runtime_enabled'=>'Runtime enabled','status'=>'Health status'] as $key=>$label){echo '<article class="slto-card"><h2><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> '.esc_html($label).'</h2><p>'.esc_html(is_bool($health[$key]??null)?(($health[$key]??false)?'Yes':'No'):(string)($health[$key]??'Unknown')).'</p></article>';}echo '<article class="slto-card"><h2><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> Metrics</h2><pre>'.esc_html(wp_json_encode($health['metrics'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></article></section>';$this->actionForm('process_jobs','Process queued jobs','overview');$this->actionForm('dispatch_events','Dispatch outbox events','overview');if($health['runtime_enabled']){$this->actionForm('deactivate_runtime','Deactivate runtime safely','overview');}
    }
    private function releases():void
    {
        $this->table('Locale Bundles',$this->s['repo']->list('bundles',[],200,0,'bundle_version DESC'),['locale_tag','bundle_version','coverage','critical_coverage','bundle_hash','status','activated_at']);echo '<h2>Build candidate bundle</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('slto_admin_action');echo '<input type="hidden" name="action" value="slto_admin_action"><input type="hidden" name="slto_action" value="build_bundle"><input type="hidden" name="return_tab" value="releases"><label for="slto-locale">Locale</label> <input id="slto-locale" name="locale" required pattern="[A-Za-z0-9-]{2,35}"> <button class="button button-primary"><span class="dashicons dashicons-hammer" aria-hidden="true"></span> Build</button></form>';
    }
    private function health():void{$report=$this->s['health']->report();echo '<h2>Health and activation gates</h2><table class="widefat striped"><thead><tr><th>Gate</th><th>Status</th></tr></thead><tbody>';foreach($report['gates'] as $key=>$value){echo '<tr><th scope="row">'.esc_html($key).'</th><td><span class="dashicons '.($value?'dashicons-yes-alt':'dashicons-warning').'" aria-hidden="true"></span> '.esc_html($value?'Ready':'Not ready').'</td></tr>';}echo '</tbody></table><h2>Evidence</h2><pre>'.esc_html(wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';}
    private function table(string $title,array $rows,array $columns):void
    {
        echo '<h2>'.esc_html($title).'</h2>';if(empty($rows)){echo '<p class="slto-empty"><span class="dashicons dashicons-info" aria-hidden="true"></span> No records.</p>';return;}echo '<div class="slto-table-scroll" role="region" aria-label="'.esc_attr($title).'" tabindex="0"><table class="widefat striped"><thead><tr>';foreach($columns as $column){echo '<th scope="col">'.esc_html(ucwords(str_replace('_',' ',$column))).'</th>';}echo '</tr></thead><tbody>';foreach($rows as $row){echo '<tr>';foreach($columns as $column){$value=$row[$column]??'';if(is_string($value)&&strlen($value)>120){$value=substr($value,0,117).'…';}echo '<td>'.esc_html((string)$value).'</td>';}echo '</tr>';}echo '</tbody></table></div>';
    }
    private function actionForm(string $action,string $label,string $tab):void{echo '<form class="slto-inline-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('slto_admin_action');echo '<input type="hidden" name="action" value="slto_admin_action"><input type="hidden" name="slto_action" value="'.esc_attr($action).'"><input type="hidden" name="return_tab" value="'.esc_attr($tab).'"><button class="button"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> '.esc_html($label).'</button></form>';}
}
