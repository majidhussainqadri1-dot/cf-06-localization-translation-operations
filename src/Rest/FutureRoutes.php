<?php

declare(strict_types=1);

namespace Sabri\Localization\Rest;

use InvalidArgumentException;
use Sabri\Localization\Application\FutureCapabilitiesFacade;
use Sabri\Localization\Contract\FutureCapabilities;
use Sabri\Localization\Security\Authorization;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class FutureRoutes
{
    private const NS = 'sabri-localization/v1';
    private const MAX_EVALUATION_BYTES = 262144;
    private const MAX_EVALUATION_NODES = 5000;

    public function __construct(private readonly FutureCapabilitiesFacade $future) {}

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/future-capabilities', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn (): WP_REST_Response => new WP_REST_Response([
                'items' => array_values($this->future->catalogue()),
                'default_state' => 'disabled',
                'activation' => 'all-governing-gates-required',
                'activation_gates' => FutureCapabilities::activationGates(),
                'activation_ready' => false,
            ], 200),
            'permission_callback' => fn (): bool => Authorization::allowed('audit') || Authorization::allowed('manage'),
        ]);

        register_rest_route(self::NS, '/future-capabilities/(?P<id>CF06-FUT-\d{3})/evaluate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response|WP_Error {
                try {
                    $body = (string)$request->get_body();
                    if (strlen($body) > self::MAX_EVALUATION_BYTES) {
                        return new WP_Error('slto_future40_payload_too_large', 'Future capability evaluation payload is too large.', ['status' => 413]);
                    }
                    $payload = $request->get_json_params();
                    $payload = is_array($payload) ? $payload : [];
                    if ($this->nodeCount($payload) > self::MAX_EVALUATION_NODES) {
                        return new WP_Error('slto_future40_payload_too_complex', 'Future capability evaluation payload is too complex.', ['status' => 413]);
                    }
                    return new WP_REST_Response(
                        $this->future->evaluate((string)$request['id'], $payload),
                        200
                    );
                } catch (InvalidArgumentException $exception) {
                    return new WP_Error('slto_future40_invalid', $exception->getMessage(), ['status' => 422]);
                } catch (Throwable $exception) {
                    error_log('SLTO Future40 evaluation failed: ' . get_class($exception));
                    return new WP_Error('slto_future40_failed', 'Future capability evaluation failed safely.', ['status' => 500]);
                }
            },
            'permission_callback' => fn (): bool => Authorization::allowed('manage'),
        ]);
    }

    private function nodeCount(array $value, int $limit = self::MAX_EVALUATION_NODES): int
    {
        $count = 0;
        $stack = [$value];
        while ([] !== $stack) {
            $current = array_pop($stack);
            foreach ($current as $item) {
                ++$count;
                if ($count > $limit) {
                    return $count;
                }
                if (is_array($item)) {
                    $stack[] = $item;
                }
            }
        }
        return $count;
    }
}
