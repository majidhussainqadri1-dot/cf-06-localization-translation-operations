<?php

declare(strict_types=1);

if (! defined('SABRI_SLTO_CONTRACT_VERSION')) {
    define('SABRI_SLTO_CONTRACT_VERSION', '1.0.0');
}
if (! defined('SLTO_BUNDLE_SIGNING_KEY')) {
    define('SLTO_BUNDLE_SIGNING_KEY', base64_encode(str_repeat('k', 32)));
}

$root = dirname(__DIR__);
require_once $root . '/src/Domain/Locale/LocaleValidator.php';
require_once $root . '/src/Domain/Locale/FallbackChainValidator.php';
require_once $root . '/src/Domain/Workflow/StateMachine.php';
require_once $root . '/src/Domain/Translation/PlaceholderValidator.php';
require_once $root . '/src/Domain/Translation/MarkupValidator.php';
require_once $root . '/src/Domain/Translation/BidiValidator.php';
require_once $root . '/src/Domain/Translation/NumberUnitGuard.php';
require_once $root . '/src/Domain/Translation/Redactor.php';
require_once $root . '/src/Domain/Translation/RiskPolicy.php';
require_once $root . '/src/Domain/Bundle/DeterministicBundle.php';
