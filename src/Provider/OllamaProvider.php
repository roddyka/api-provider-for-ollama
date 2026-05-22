<?php

declare(strict_types=1);

namespace WordPress\OllamaLocalAiProvider\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\OllamaLocalAiProvider\Metadata\OllamaModelMetadataDirectory;
use WordPress\OllamaLocalAiProvider\Models\OllamaTextGenerationModel;
use WordPress\OllamaLocalAiProvider\Support\OllamaConfig;

/**
 * Class for the AI Provider for Ollama Local.
 *
 * @since 0.1.0
 */
class OllamaProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function baseUrl(): string
    {
        return OllamaConfig::getBaseUrl();
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new OllamaTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'ollama-local',
            'Ollama Local',
            ProviderTypeEnum::server(),
            'https://github.com/ollama/ollama/blob/main/docs/openai.md',
            RequestAuthenticationMethod::apiKey(),
        ];

        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            if (function_exists('__')) {
                $providerMetadataArgs[] = __('Self-hosted text generation through a local Ollama server.', 'ai-provider-for-ollama-local');
            } else {
                $providerMetadataArgs[] = 'Self-hosted text generation through a local Ollama server.';
            }
        }

        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OllamaModelMetadataDirectory();
    }

}
