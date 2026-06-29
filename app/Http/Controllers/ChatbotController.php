<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Server-side proxy to the standalone JBG chatbot microservice (Python/FastAPI).
// The browser widget only ever talks to this endpoint — the bot's basic-auth
// creds and JWT never reach the client. Mirrors the bot's two endpoints:
// POST /generate_token (basic auth) then POST /forward_message (bearer token).
class ChatbotController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $base = rtrim((string) config('services.chatbot.url'), '/');
        $user = (string) config('services.chatbot.user');
        $pass = (string) config('services.chatbot.pass');
        $timeout = (int) config('services.chatbot.timeout', 30);

        if ($base === '' || $user === '' || $pass === '') {
            return response()->json(
                ['reply' => 'Perkhidmatan chatbot belum dikonfigurasi.'],
                503,
            );
        }

        // Stable per-session conversation id (bot expects an int session_id, and
        // uses it to thread conversation history).
        $sid = $request->session()->get('chatbot_sid');
        if (! $sid) {
            $sid = random_int(100000, 2147483647);
            $request->session()->put('chatbot_sid', $sid);
        }

        try {
            $tokenRes = Http::timeout($timeout)
                ->withBasicAuth($user, $pass)
                ->acceptJson()
                ->post("{$base}/generate_token");

            $token = $tokenRes->successful() ? $tokenRes->json('access_token') : null;

            if (! $token) {
                Log::warning('Chatbot token request failed', ['status' => $tokenRes->status()]);

                return response()->json(
                    ['reply' => 'Maaf, perkhidmatan chatbot tidak tersedia buat masa ini.'],
                    502,
                );
            }

            $msgRes = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->post("{$base}/forward_message", [
                    'message' => $data['message'],
                    'session_id' => $sid,
                    // Bot's Pydantic model types user_name as str — send '' (not
                    // null) for guests, else it rejects the body with 422.
                    'user_name' => $request->user()?->name ?? '',
                ]);

            if (! $msgRes->successful()) {
                Log::warning('Chatbot message request failed', ['status' => $msgRes->status()]);

                return response()->json(
                    ['reply' => 'Maaf, berlaku ralat. Sila cuba lagi.'],
                    502,
                );
            }

            return response()->json([
                'reply' => $msgRes->json('content_raw') ?: 'Maaf, tiada jawapan diterima.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot proxy error', ['msg' => $e->getMessage()]);

            return response()->json(
                ['reply' => 'Maaf, perkhidmatan chatbot tidak dapat dihubungi.'],
                502,
            );
        }
    }
}
