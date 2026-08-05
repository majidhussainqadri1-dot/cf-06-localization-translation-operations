<?php

declare(strict_types=1);

use Sabri\Localization\Contract\Manifest;
use Sabri\Localization\Plugin;

if (! function_exists('slto_manifest')) {
    function slto_manifest(): array { return Manifest::get(); }
}
if (! function_exists('slto_get_supported_locales')) {
    function slto_get_supported_locales(bool $public = true): array {
        $service=Plugin::instance()->service('locale');return $service ? $service->list($public) : array();
    }
}
if (! function_exists('slto_resolve_locale')) {
    function slto_resolve_locale(string $requested): array {
        $service=Plugin::instance()->service('locale');return $service ? $service->resolve($requested) : array('requested'=>$requested,'resolved'=>'en-US','direction'=>'ltr','status'=>'unavailable','fallback_used'=>true,'chain'=>array());
    }
}
if (! function_exists('slto_get_active_bundle')) {
    function slto_get_active_bundle(string $locale): ?array {
        if(!Plugin::runtimeEnabled()){return null;}$service=Plugin::instance()->service('bundle');return $service ? $service->publicBundle($locale) : null;
    }
}
add_filter('slto_language_options',static fn(array $options):array=>array_merge($options,slto_get_supported_locales(true)));
add_filter('sabri_localization_manifest',static fn(array $manifest):array=>array_merge($manifest,slto_manifest()));
