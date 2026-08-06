<?php

declare(strict_types=1);

namespace Sabri\Localization\Cli;

use Sabri\Localization\Application\BundleService;
use Sabri\Localization\Application\HealthService;
use Sabri\Localization\Application\MigrationService;
use Sabri\Localization\Infrastructure\JobQueue;
use Sabri\Localization\Infrastructure\Outbox;

final class Commands
{
    public function __construct(private readonly HealthService $health,private readonly BundleService $bundles,private readonly MigrationService $migration,private readonly JobQueue $jobs,private readonly Outbox $outbox){}
    public function register():void
    {
        if(!defined('WP_CLI')||!WP_CLI){return;}\WP_CLI::add_command('slto status',fn()=>\WP_CLI::print_value($this->health->report(),['format'=>'json']));\WP_CLI::add_command('slto inventory',fn()=>\WP_CLI::print_value($this->migration->inventory(),['format'=>'json']));\WP_CLI::add_command('slto jobs',fn()=>\WP_CLI::print_value($this->jobs->run(100),['format'=>'json']));\WP_CLI::add_command('slto events',fn()=>\WP_CLI::success('Dispatched '.$this->outbox->dispatch(100).' localization events.'));\WP_CLI::add_command('slto bundle-build',function($args){$locale=(string)($args[0]??'');\WP_CLI::print_value($this->bundles->build($locale),['format'=>'json']);});
    }
}
