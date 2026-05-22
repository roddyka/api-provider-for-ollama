<?php

declare(strict_types=1);

namespace WordPress\OllamaLocalAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\OllamaLocalAiProvider\Provider\OllamaProvider;

/**
 * Class for an Ollama text generation model using the OpenAI-compatible Chat Completions API.
 *
 * @since 0.1.0
 */
class OllamaTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request(
            $method,
            OllamaProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
