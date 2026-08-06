<?php

declare(strict_types=1);

namespace Sabri\Localization;

use Sabri\Localization\Admin\AdminPage;
use Sabri\Localization\Application\BundleService;
use Sabri\Localization\Application\ContentLinkService;
use Sabri\Localization\Application\ExtractionService;
use Sabri\Localization\Application\FeedbackService;
use Sabri\Localization\Application\HealthService;
use Sabri\Localization\Application\IntegrationService;
use Sabri\Localization\Application\LocaleService;
use Sabri\Localization\Application\MachineTranslationService;
use Sabri\Localization\Application\MetricsService;
use Sabri\Localization\Application\MigrationService;
use Sabri\Localization\Application\PrivacyService;
use Sabri\Localization\Application\ProjectService;
use Sabri\Localization\Application\ProviderService;
use Sabri\Localization\Application\QaEvidenceService;
use Sabri\Localization\Application\QaService;
use Sabri\Localization\Application\ReleaseApprovalService;
use Sabri\Localization\Application\ResourceService;
use Sabri\Localization\Application\TerminologyService;
use Sabri\Localization\Application\TranslationService;
use Sabri\Localization\Cli\Commands;
use Sabri\Localization\Contract\IntegrationRegistry;
use Sabri\Localization\Infrastructure\Activator;
use Sabri\Localization\Infrastructure\Crypto;
use Sabri\Localization\Infrastructure\JobQueue;
use Sabri\Localization\Infrastructure\Outbox;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocalizationRepository;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Provider\MachineTranslationProvider;
use Sabri\Localization\Provider\NullProvider;
use Sabri\Localization\Rest\Routes;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;
    private array $services = array();

    public static function instance(): self { return self::$instance ??= new self(); }

    public function boot(): void
    {
        if ($this->booted) { return; }
        $this->booted = true;
        Activator::maybeUpgrade();

        add_action('init', static function(): void {
            load_plugin_textdomain('sabri-localization-translation-operations', false, dirname(plugin_basename(SABRI_SLTO_FILE)).'/languages');
            IntegrationRegistry::publishManifest();
        });

        $crypto = new Crypto();
        $repo = new LocalizationRepository($crypto);
        $audit = new AuditRepository();
        $tx = new Transaction();
        $outbox = new Outbox();
        $jobs = new JobQueue();

        $integrations = new IntegrationService($repo,$audit,$outbox,$tx);
        $extraction = new ExtractionService($repo,$audit,$tx);
        $qaEvidence = new QaEvidenceService($repo,$audit,$tx);
        $releaseApprovals = new ReleaseApprovalService($repo,$audit,$tx);
        $locale = new LocaleService($repo,$audit,$outbox,$tx);
        $resource = new ResourceService($repo,$audit,$outbox,$tx);
        $qa = new QaService($repo,$resource);
        $translation = new TranslationService($repo,$resource,$qa,$audit,$outbox,$tx);
        $project = new ProjectService($repo,$audit,$outbox,$tx);
        $terminology = new TerminologyService($repo,$audit,$outbox,$tx);
        $bundle = new BundleService($repo,$translation,$qa,$audit,$outbox,$tx,$integrations,$releaseApprovals);
        $feedback = new FeedbackService($repo,$audit,$outbox,$tx);
        $metrics = new MetricsService($repo);
        $health = new HealthService($crypto,$metrics,$integrations);
        $privacy = new PrivacyService($jobs,$audit,$tx);
        $migration = new MigrationService($audit);
        $contentLinks = new ContentLinkService($repo,$audit,$tx);
        $providers = new ProviderService($repo,$audit,$tx);

        $provider = apply_filters('slto_machine_translation_provider', new NullProvider(), $repo);
        if (! $provider instanceof MachineTranslationProvider) { $provider = new NullProvider(); }
        $mt = new MachineTranslationService($repo,$resource,$translation,$audit,$outbox,$tx,$provider);

        $jobs->register('privacy_erasure', array($privacy,'processErasure'));
        $jobs->register('outbox_dispatch', fn() => $outbox->dispatch(100));
        $jobs->register('mark_stale', function(array $payload) use ($repo): void {
            if (! empty($payload['resource_uuid'])) {
                $repo->markDependentUnitsStale((string)$payload['resource_uuid'], (string)($payload['reason'] ?? 'source_changed'));
            }
        });

        $this->services = compact(
            'crypto','repo','audit','tx','outbox','jobs','integrations','extraction','qaEvidence','releaseApprovals',
            'locale','resource','qa','translation','project','terminology','bundle','feedback','metrics','health','privacy',
            'migration','contentLinks','providers','mt'
        );

        (new Routes($this->services))->registerHooks();
        (new AdminPage($this->services))->registerHooks();
        (new Commands($health,$bundle,$migration,$jobs,$outbox))->register();

        add_action('slto_process_jobs', fn() => $jobs->run(50));
        add_action('slto_process_jobs', fn() => $outbox->dispatch(100));
        add_action('slto_daily_reconciliation', function() use ($repo): void {
            $repo->list('vendor_jobs',array('status'=>'accepted'),100);
            $repo->cleanupOperationalState();
            delete_expired_transients(true);
        });

        add_filter('wp_privacy_personal_data_exporters', function(array $exporters) use ($privacy): array {
            $exporters['sabri-localization'] = array(
                'exporter_friendly_name'=>'Sabri Localization Operations',
                'callback'=>function(string $email,int $page=1) use ($privacy): array {
                    $user=get_user_by('email',$email);
                    if(!$user){return array('data'=>array(),'done'=>true);}
                    $export=$privacy->exportUser((int)$user->ID,$page);
                    return array('data'=>array(array('group_id'=>'sabri-localization','group_label'=>'Localization Operations','item_id'=>'slto-'.$user->ID.'-'.$page,'data'=>array(array('name'=>'Localization data','value'=>wp_json_encode($export['data']))))),'done'=>$export['done']);
                },
            );
            return $exporters;
        });

        add_filter('wp_privacy_personal_data_erasers', function(array $erasers) use ($privacy): array {
            $erasers['sabri-localization'] = array(
                'eraser_friendly_name'=>'Sabri Localization Operations',
                'callback'=>function(string $email,int $page=1) use ($privacy): array {
                    $user=get_user_by('email',$email);
                    if(!$user||$page>1){return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);}
                    $job=$privacy->requestErasure((int)$user->ID,'wordpress_privacy_request');
                    return array('items_removed'=>false,'items_retained'=>true,'messages'=>array('Localization erasure was queued for audited background processing. Reference: '.$job),'done'=>true);
                },
            );
            return $erasers;
        });
    }

    public static function runtimeEnabled(): bool { return (bool)get_option('slto_runtime_enabled',false); }
    public function service(string $name): mixed { return $this->services[$name]??null; }
    private function __construct() {}
}
