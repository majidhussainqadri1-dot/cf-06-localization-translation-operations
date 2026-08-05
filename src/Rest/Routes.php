<?php

declare(strict_types=1);

namespace Sabri\Localization\Rest;

use InvalidArgumentException;
use Sabri\Localization\Application\LocaleService;
use Sabri\Localization\Application\ResourceService;
use Sabri\Localization\Plugin;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Routes
{
    private const NAMESPACE = 'sabri-localization/v1';

    public function __construct(
        private readonly LocaleService $locales,
        private readonly ResourceService $resources
    ) {
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'status'),
            'permission_callback' => array($this, 'canManage'),
        ));
        register_rest_route(self::NAMESPACE, '/locales', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'locales'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'registerLocale'),
                'permission_callback' => array($this, 'canManage'),
            ),
        ));
        register_rest_route(self::NAMESPACE, '/resolve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'resolve'),
            'permission_callback' => '__return_true',
            'args' => array('locale' => array('type' => 'string', 'required' => true)),
        ));
        register_rest_route(self::NAMESPACE, '/resources', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'registerResource'),
            'permission_callback' => array($this, 'canManage'),
        ));
    }

    public function canManage(): bool
    {
        return current_user_can('manage_sabri_localization');
    }

    public function status(): WP_REST_Response
    {
        return new WP_REST_Response(array(
            'plugin_version' => SABRI_SLTO_VERSION,
            'schema_version' => (string) get_option('slto_schema_version', ''),
            'runtime_enabled' => Plugin::runtimeEnabled(),
            'phase' => 'C6-B foundation',
            'truth_status' => array(
                'specified' => true,
                'coded' => 'foundation-partial',
                'packaged' => 'external-evidence-required',
                'automated_qa' => 'external-evidence-required',
                'staging_accepted' => false,
                'live_deployed' => false,
                'operational' => false,
            ),
        ));
    }

    public function locales(): WP_REST_Response|WP_Error
    {
        if (! Plugin::runtimeEnabled() && ! $this->canManage()) {
            return new WP_Error('slto_runtime_disabled', 'Localization runtime is not activated.', array('status' => 503));
        }
        return new WP_REST_Response(array('items' => $this->locales->publicEnabled()));
    }

    public function resolve(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! Plugin::runtimeEnabled() && ! $this->canManage()) {
            return new WP_Error('slto_runtime_disabled', 'Localization runtime is not activated.', array('status' => 503));
        }
        return new WP_REST_Response($this->locales->resolve((string) $request->get_param('locale')));
    }

    public function registerLocale(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->locales->register((array) $request->get_json_params()), 201);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('slto_invalid_locale', $exception->getMessage(), array('status' => 422));
        } catch (Throwable $exception) {
            return new WP_Error('slto_locale_write_failed', 'Locale registry write failed.', array('status' => 500));
        }
    }

    public function registerResource(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->resources->register((array) $request->get_json_params()), 201);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('slto_invalid_resource', $exception->getMessage(), array('status' => 422));
        } catch (Throwable $exception) {
            return new WP_Error('slto_resource_write_failed', 'Resource catalog write failed.', array('status' => 500));
        }
    }
}
