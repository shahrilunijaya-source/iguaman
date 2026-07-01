<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ARCH-06 — adapter for the standalone JBG chatbot microservice (Python/FastAPI).
 * Wraps the two-step protocol (POST /generate_token with basic auth, then POST
 * /forward_message with the bearer token) so the rest of the app depends on this
 * contract, not on the HTTP details or the bot credentials. Never throws:
 * transport/auth failures become a friendly Malay reply + an upstream status code.
 */
class ChatbotClient
{
    public function isConfigured(): bool
    {
        return $this->base() !== '' && $this->user() !== '' && $this->pass() !== '';
    }

    /**
     * Ask the bot a message on a stable session.
     *
     * @return array{reply: string, status: int}
     */
    public function ask(string $message, int $sessionId, string $userName): array
    {
        if (! $this->isConfigured()) {
            return ['reply' => 'Perkhidmatan chatbot belum dikonfigurasi.', 'status' => 503];
        }

        try {
            $token = $this->token();
            if ($token === null) {
                return ['reply' => 'Maaf, perkhidmatan chatbot tidak tersedia buat masa ini.', 'status' => 502];
            }

            $res = Http::timeout($this->timeout())
                ->withToken($token)
                ->acceptJson()
                ->post("{$this->base()}/forward_message", [
                    'message' => $message,
                    'session_id' => $sessionId,
                    // Bot's Pydantic model types user_name as str — send '' (not null)
                    // for guests, else it rejects the body with 422.
                    'user_name' => $userName,
                ]);

            if (! $res->successful()) {
                Log::warning('Chatbot message request failed', ['status' => $res->status()]);

                return ['reply' => 'Maaf, berlaku ralat. Sila cuba lagi.', 'status' => 502];
            }

            return ['reply' => $res->json('content_raw') ?: 'Maaf, tiada jawapan diterima.', 'status' => 200];
        } catch (\Throwable $e) {
            Log::error('Chatbot proxy error', ['msg' => $e->getMessage()]);

            return ['reply' => 'Maaf, perkhidmatan chatbot tidak dapat dihubungi.', 'status' => 502];
        }
    }

    /** Fetch a short-lived bearer token via basic auth; null when the bot rejects it. */
    private function token(): ?string
    {
        $res = Http::timeout($this->timeout())
            ->withBasicAuth($this->user(), $this->pass())
            ->acceptJson()
            ->post("{$this->base()}/generate_token");

        if (! $res->successful()) {
            Log::warning('Chatbot token request failed', ['status' => $res->status()]);

            return null;
        }

        return $res->json('access_token') ?: null;
    }

    private function base(): string
    {
        return rtrim((string) config('services.chatbot.url'), '/');
    }

    private function user(): string
    {
        return (string) config('services.chatbot.user');
    }

    private function pass(): string
    {
        return (string) config('services.chatbot.pass');
    }

    private function timeout(): int
    {
        return (int) config('services.chatbot.timeout', 30);
    }
}
