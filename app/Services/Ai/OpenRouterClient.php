<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenRouter chat-completions client. The model is expected to answer in JSON
 * (requested via response_format); callers parse and validate the content.
 */
final class OpenRouterClient implements AiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $key,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function chat(array $messages): string
    {
        if ($this->key === '') {
            throw new AiRequestException('No AI API key configured — set AI_API_KEY in .env.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->key,
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => 'Omahub',
            ])
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (ConnectionException) {
            throw new AiRequestException('Could not reach the AI provider (OpenRouter).');
        }

        if ($response->status() === 401) {
            throw new AiRequestException('The AI provider rejected the API key (401).');
        }

        if ($response->status() === 429) {
            throw new AiRequestException('The AI provider rate limited the request (429).');
        }

        if ($response->failed()) {
            throw new AiRequestException('The AI provider request failed ('.$response->status().').');
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiRequestException('The AI provider returned an empty response.');
        }

        return $content;
    }
}
