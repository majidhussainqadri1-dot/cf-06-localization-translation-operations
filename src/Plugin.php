<?php

declare(strict_types=1);

namespace Sabri\Localization;

use Sabri\Localization\Admin\AdminPage;
use Sabri\Localization\Application\LocaleService;
use Sabri\Localization\Application\ResourceService;
use Sabri\Localization\Infrastructure\Activator;
use Sabri\Localization\Infrastructure\Repository\AuditRepository;
use Sabri\Localization\Infrastructure\Repository\LocaleRepository;
use Sabri\Localization\Infrastructure\Repository\ResourceRepository;
use Sabri\Localization\Infrastructure\Transaction;
use Sabri\Localization\Rest\Routes;

final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        Activator::maybeUpgrade();

        add_action(
            'init',
            static function (): void {
                load_plugin_textdomain(
                    'sabri-localization-translation-operations',
                    false,
                    dirname(plugin_basename(SABRI_SLTO_FILE)) . '/languages'
                );
            }
        );

        $localeRepository   = new LocaleRepository();
        $resourceRepository = new ResourceRepository();
        $auditRepository    = new AuditRepository();
        $transaction        = new Transaction();
        $localeService      = new LocaleService($localeRepository, $auditRepository, $transaction);
        $resourceService    = new ResourceService($localeRepository, $resourceRepository, $auditRepository, $transaction);

        (new Routes($localeService, $resourceService))->registerHooks();
        (new AdminPage($localeService))->registerHooks();
    }

    public static function runtimeEnabled(): bool
    {
        return (bool) get_option('slto_runtime_enabled', false);
    }

    private function __construct()
    {
    }
}
