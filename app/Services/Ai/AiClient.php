<?php

namespace App\Services\Ai;

/**
 * Minimal LLM chat client abstraction. Implementations send a chat conversation
 * and return the assistant's text reply, throwing an exception on failure.
 */
interface AiClient
{
    /**
     * Send a chat completion and return the assistant's text content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string;
}
