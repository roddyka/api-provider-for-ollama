<?php

declare(strict_types=1);

namespace WordPress\OllamaLocalAiProvider\Support;

/**
 * Centralized configuration for Ollama Local provider.
 *
 * @since 0.1.0
 */
class OllamaConfig
{
    public const OPTION_BASE_URL = 'ollama_local_ai_base_url';
    public const OPTION_DEFAULT_MODEL = 'ollama_local_ai_default_model';
    public const DEFAULT_BASE_URL = 'http://127.0.0.1:11434/v1';

    /**
     * Gets the base URL from option/constant/env/default in this order.
     *
     * @since 0.1.0
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {
        $fromOption = get_option(self::OPTION_BASE_URL, '');
        if (is_string($fromOption) && $fromOption !== '') {
            return self::normalizeBaseUrl($fromOption);
        }

        if (defined('OLLAMA_API_BASE_URL') && is_string(constant('OLLAMA_API_BASE_URL'))) {
            return self::normalizeBaseUrl(constant('OLLAMA_API_BASE_URL'));
        }

        $fromEnv = getenv('OLLAMA_API_BASE_URL');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return self::normalizeBaseUrl($fromEnv);
        }

        return self::DEFAULT_BASE_URL;
    }

    /**
     * Gets the default model configured by the user.
     *
     * @since 0.1.0
     *
     * @return string
     */
    public static function getDefaultModel(): string
    {
        $model = get_option(self::OPTION_DEFAULT_MODEL, '');
        return is_string($model) ? trim($model) : '';
    }

    /**
     * Normalizes a base URL and appends /v1 if missing.
     *
     * @since 0.1.0
     *
     * @param string $url Base URL.
     * @return string
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $normalized = rtrim(trim($url), '/');

        if ($normalized === '') {
            return self::DEFAULT_BASE_URL;
        }

        if (!preg_match('#/v1$#', $normalized)) {
            $normalized .= '/v1';
        }

        return $normalized;
    }
}
