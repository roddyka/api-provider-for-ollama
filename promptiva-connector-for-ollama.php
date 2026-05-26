<?php

/**
 * Plugin Name: Promptiva Connector for Ollama
 * Plugin URI: https://github.com/roddyka/api-provider-for-ollama
 * Description: Connect WordPress 7.0 AI features to a self-hosted Ollama server — no API key required.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.1.0
 * Author: roddyka
 * Author URI: https://github.com/roddyka
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-ollama-local
 *
 * @package WordPress\OllamaLocalAiProvider
 */

declare(strict_types=1);

namespace WordPress\OllamaLocalAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\OllamaLocalAiProvider\Provider\OllamaProvider;
use WordPress\OllamaLocalAiProvider\Support\OllamaConfig;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for Ollama with the AI Client.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(OllamaProvider::class)) {
        return;
    }

    $registry->registerProvider(OllamaProvider::class);

    // Ollama's OpenAI-compatible endpoint ignores the bearer token, but the AI
    // Client unconditionally calls the provider's request authenticator. Inject
    // a placeholder API key so list-models / chat requests are signed and the
    // provider is considered configured.
    try {
        $registry->setProviderRequestAuthentication(
            'ollama-local',
            new ApiKeyRequestAuthentication('ollama-local')
        );
    } catch (\Throwable $e) {
        // Registry may not yet accept by ID in older AiClient versions; ignore.
    }
}

/**
 * Registers connector metadata for the Connectors admin page.
 *
 * This ensures the provider appears in Settings > Connectors and keeps
 * plugin activation metadata stable, even if auto-discovery order changes.
 *
 * @since 0.1.0
 *
 * @param \WP_Connector_Registry $registry Connector registry instance.
 * @return void
 */
function register_connector($registry): void
{
    if (!is_object($registry) || !method_exists($registry, 'register')) {
        return;
    }

    $connectorId = 'ollama-local';

    if (method_exists($registry, 'is_registered') && $registry->is_registered($connectorId)) {
        $registry->unregister($connectorId);
    }

    $registry->register(
        $connectorId,
        [
            'name' => __('Promptiva Connector for Ollama', 'ai-provider-for-ollama-local'),
            'description' => __('Self-hosted text generation through a local Ollama server.', 'ai-provider-for-ollama-local'),
            'type' => 'ai_provider',
            'plugin' => [
                'file' => plugin_basename(__FILE__),
                'is_active' => static function (): bool {
                    return true;
                },
            ],
            'authentication' => [
                'method' => 'none',
            ],
        ]
    );
}

/**
 * Prepends the configured Ollama default model to preferred text models.
 *
 * @since 0.1.0
 *
 * @param array<int, array{string, string}> $preferredModels Current preferred models.
 * @return array<int, array{string, string}>
 */
function filter_preferred_text_models(array $preferredModels): array
{
    $defaultModel = OllamaConfig::getDefaultModel();
    if ($defaultModel === '') {
        return $preferredModels;
    }

    $preferredModels = array_values(
        array_filter(
            $preferredModels,
            static function (array $item) use ($defaultModel): bool {
                return !($item[0] === 'ollama-local' && $item[1] === $defaultModel);
            }
        )
    );

    array_unshift($preferredModels, ['ollama-local', $defaultModel]);
    return $preferredModels;
}

/**
 * Fetches available models from Ollama's OpenAI-compatible endpoint.
 *
 * @since 0.1.0
 *
 * @param string $baseUrl Effective base URL.
 * @return array{ok: bool, models: array<int, string>, message: string}
 */
function fetch_ollama_models(string $baseUrl): array
{
    $url = rtrim($baseUrl, '/') . '/models';
    $response = wp_remote_get(
        $url,
        [
            'timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]
    );

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'models' => [],
            'message' => 'unreachable: ' . $response->get_error_message(),
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'models' => [],
            'message' => 'HTTP ' . $status,
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
        return [
            'ok' => false,
            'models' => [],
            'message' => 'invalid response',
        ];
    }

    $models = [];
    foreach ($data['data'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = isset($item['id']) && is_string($item['id']) ? trim($item['id']) : '';
        if ($id !== '') {
            $models[] = $id;
        }
    }

    $models = array_values(array_unique($models));
    sort($models);

    return [
        'ok' => true,
        'models' => $models,
        'message' => '',
    ];
}

/**
 * Registers the Ollama Local settings (base URL and default model).
 *
 * Uses the standard Settings API so the form posts to options.php and
 * is saved/sanitized by WordPress core, no custom handler needed.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_settings(): void
{
    register_setting(
        'ollama_local_ai_provider',
        OllamaConfig::OPTION_BASE_URL,
        [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = is_string($value) ? trim($value) : '';
                if ($value === '') {
                    return '';
                }
                return esc_url_raw($value);
            },
            'default' => '',
            'show_in_rest' => false,
        ]
    );

    register_setting(
        'ollama_local_ai_provider',
        OllamaConfig::OPTION_DEFAULT_MODEL,
        [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = is_string($value) ? trim($value) : '';
                return sanitize_text_field($value);
            },
            'default' => '',
            'show_in_rest' => false,
        ]
    );
}

/**
 * Module ID used to enqueue the custom Ollama connector card.
 *
 * @since 0.1.0
 */
const CONNECTOR_SCRIPT_MODULE_ID = 'promptiva-connector-for-ollama/connector';

/**
 * Registers a REST endpoint to save the Ollama Local settings from the card.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_rest_endpoint(): void
{
    register_rest_route(
        'promptiva-connector-for-ollama/v1',
        '/settings',
        [
            'methods'             => 'POST',
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'args'                => [
                'base_url'      => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => '',
                ],
                'default_model' => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => '',
                ],
            ],
            'callback'            => static function ($request) {
                $rawBaseUrl = is_string($request->get_param('base_url'))
                    ? trim($request->get_param('base_url'))
                    : '';
                $baseUrl    = $rawBaseUrl === '' ? '' : esc_url_raw($rawBaseUrl);
                $model      = sanitize_text_field((string) $request->get_param('default_model'));

                update_option(OllamaConfig::OPTION_BASE_URL, $baseUrl);
                update_option(OllamaConfig::OPTION_DEFAULT_MODEL, $model);

                return [
                    'base_url'           => $baseUrl,
                    'default_model'      => $model,
                    'effective_base_url' => OllamaConfig::getBaseUrl(),
                ];
            },
        ]
    );
}

/**
 * Registers and enqueues the connector card script module on the
 * Connectors admin screen.
 *
 * @since 0.1.0
 *
 * @return void
 */
function enqueue_connector_card_module(): void
{
    if (!function_exists('wp_register_script_module') || !function_exists('wp_enqueue_script_module')) {
        return;
    }

    wp_register_script_module(
        CONNECTOR_SCRIPT_MODULE_ID,
        plugins_url('assets/connector-card.js', __FILE__),
        [
            [
                'id'     => '@wordpress/connectors',
                'import' => 'static',
            ],
        ],
        '0.1.0'
    );

    wp_enqueue_script_module(CONNECTOR_SCRIPT_MODULE_ID);

    // Ensure wp.apiFetch is available for the module's save flow.
    if (function_exists('wp_enqueue_script')) {
        wp_enqueue_script('wp-api-fetch');
    }
}

/**
 * Provides Ollama-specific data to the connector card script module.
 *
 * @since 0.1.0
 *
 * @param array<string, mixed> $data Script module data.
 * @return array<string, mixed>
 */
function filter_connector_card_script_module_data(array $data): array
{
    $baseUrl     = OllamaConfig::getBaseUrl();
    $rawBaseUrl  = (string) get_option(OllamaConfig::OPTION_BASE_URL, '');
    $diagnostics = fetch_ollama_models($baseUrl);

    $data['baseUrl']          = $rawBaseUrl;
    $data['effectiveBaseUrl'] = $baseUrl;
    $data['defaultBaseUrl']   = OllamaConfig::DEFAULT_BASE_URL;
    $data['defaultModel']     = OllamaConfig::getDefaultModel();
    $data['models']           = $diagnostics['models'];
    $data['reachable']        = (bool) $diagnostics['ok'];
    $data['statusMessage']    = $diagnostics['message'];
    $data['restPath']         = '/promptiva-connector-for-ollama/v1/settings';

    return $data;
}

add_action('plugins_loaded', __NAMESPACE__ . '\\register_provider', 5);
add_action('init', __NAMESPACE__ . '\\register_provider', 5);
add_action('wp_connectors_init', __NAMESPACE__ . '\\register_connector', 20);
add_action('admin_init', __NAMESPACE__ . '\\register_settings');
add_action('rest_api_init', __NAMESPACE__ . '\\register_rest_endpoint');
add_action('options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_card_module');
add_filter('script_module_data_' . CONNECTOR_SCRIPT_MODULE_ID, __NAMESPACE__ . '\\filter_connector_card_script_module_data');
add_filter('wpai_preferred_text_models', __NAMESPACE__ . '\\filter_preferred_text_models', 10, 1);

/**
 * Declares AI credentials as available when Ollama Local is configured.
 *
 * The core `has_ai_credentials()` helper only considers connectors that use
 * the `api_key` authentication method. Ollama uses `none`, so we surface
 * credential availability via this filter whenever a base URL or default
 * model is configured.
 *
 * @since 0.1.0
 *
 * @param bool                                $hasCredentials Current value.
 * @param array<string, array<string, mixed>> $connectors     Registered AI connectors.
 * @return bool
 */
add_filter(
    'wpai_has_ai_credentials',
    static function ($hasCredentials, $connectors) {
        if ($hasCredentials) {
            return $hasCredentials;
        }

        if (!is_array($connectors) || !isset($connectors['ollama-local'])) {
            return $hasCredentials;
        }

        $baseUrl = (string) get_option(OllamaConfig::OPTION_BASE_URL, '');
        $model   = OllamaConfig::getDefaultModel();

        return ($baseUrl !== '' || $model !== '');
    },
    10,
    2
);

/**
 * Treats credentials as valid when Ollama is reachable.
 *
 * The default validity check calls AiClient text generation support, which
 * does not currently account for this provider. We probe the configured
 * Ollama endpoint directly and short-circuit the check when it responds.
 *
 * @since 0.1.0
 *
 * @param bool|null $valid Current validity (null means run default check).
 * @return bool|null
 */
add_filter(
    'wpai_pre_has_valid_credentials_check',
    static function ($valid) {
        if (null !== $valid) {
            return $valid;
        }

        $diagnostics = fetch_ollama_models(OllamaConfig::getBaseUrl());
        if ($diagnostics['ok']) {
            return true;
        }

        return null;
    },
    10,
    1
);

/**
 * Allows requests to the configured Ollama host to bypass WordPress's
 * private-IP guard used by `wp_safe_remote_request()`.
 *
 * The AI Client adapter routes all provider HTTP calls through
 * `wp_safe_remote_request()`, which rejects private/loopback hosts via
 * `wp_http_validate_url()`. We re-enable just the configured Ollama host so
 * self-hosted setups (Docker bridges, LAN servers, localhost) work.
 *
 * @since 0.1.0
 *
 * @param bool   $isExternal Current external state.
 * @param string $host       Requested host.
 * @return bool
 */
add_filter(
    'http_request_host_is_external',
    static function ($isExternal, $host) {
        if ($isExternal) {
            return $isExternal;
        }

        $parsed = wp_parse_url(OllamaConfig::getBaseUrl());
        $allowedHost = is_array($parsed) && isset($parsed['host']) ? (string) $parsed['host'] : '';

        if ($allowedHost !== '' && strcasecmp($allowedHost, (string) $host) === 0) {
            return true;
        }

        return $isExternal;
    },
    10,
    2
);

/**
 * Adds the configured Ollama port to the list of safe HTTP ports.
 *
 * `wp_http_validate_url()` (used by `wp_safe_remote_request()`) only accepts
 * ports 80, 443, and 8080 by default. Ollama defaults to 11434, so we widen
 * the allow-list to include whichever port the user configured.
 *
 * @since 0.1.0
 *
 * @param int[]  $allowedPorts Current allowed ports.
 * @param string $host         Requested host.
 * @return int[]
 */
add_filter(
    'http_allowed_safe_ports',
    static function ($allowedPorts, $host) {
        $parsed = wp_parse_url(OllamaConfig::getBaseUrl());
        if (!is_array($parsed)) {
            return $allowedPorts;
        }

        $allowedHost = isset($parsed['host']) ? (string) $parsed['host'] : '';
        $allowedPort = isset($parsed['port']) ? (int) $parsed['port'] : 0;

        if ($allowedPort <= 0 || $allowedHost === '') {
            return $allowedPorts;
        }

        if (strcasecmp($allowedHost, (string) $host) !== 0) {
            return $allowedPorts;
        }

        if (!is_array($allowedPorts)) {
            $allowedPorts = array();
        }

        if (!in_array($allowedPort, $allowedPorts, true)) {
            $allowedPorts[] = $allowedPort;
        }

        return $allowedPorts;
    },
    10,
    2
);

/**
 * Raises the HTTP timeout for outbound requests to the configured Ollama host.
 *
 * Local Ollama installs can take well over the default 5-30 second WordPress
 * timeout to respond on the first inference (model cold start, larger
 * reasoning models such as qwen3). Without a higher timeout, calls fail with
 * `cURL error 28: Operation timed out`.
 *
 * @since 0.1.0
 *
 * @param array<string, mixed> $args HTTP request arguments.
 * @param string               $url  Target URL.
 * @return array<string, mixed>
 */
add_filter(
    'http_request_args',
    static function ($args, $url) {
        $parsed = wp_parse_url(OllamaConfig::getBaseUrl());
        if (!is_array($parsed) || empty($parsed['host'])) {
            return $args;
        }

        $targetParsed = wp_parse_url((string) $url);
        if (!is_array($targetParsed) || empty($targetParsed['host'])) {
            return $args;
        }

        if (strcasecmp((string) $parsed['host'], (string) $targetParsed['host']) !== 0) {
            return $args;
        }

        if (!is_array($args)) {
            $args = array();
        }

        $minTimeout = 300;
        if (!isset($args['timeout']) || (int) $args['timeout'] < $minTimeout) {
            $args['timeout'] = $minTimeout;
        }

        return $args;
    },
    10,
    2
);