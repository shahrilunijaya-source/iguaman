<?php

namespace App\Http\Controllers;

use App\Support\ChatbotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Server-side proxy to the standalone JBG chatbot microservice (Python/FastAPI).
// The browser widget only ever talks to this endpoint - the bot's basic-auth creds
// and JWT never reach the client. The two-step token+forward protocol lives in the
// ChatbotClient adapter (ARCH-06); this controller owns transport + the session id.
class ChatbotController extends Controller
{
    public function ask(Request $request, ChatbotClient $bot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Stable per-session conversation id (bot expects an int session_id, and uses
        // it to thread conversation history).
        $sid = $request->session()->get('chatbot_sid');
        if (! $sid) {
            $sid = random_int(100000, 2147483647);
            $request->session()->put('chatbot_sid', $sid);
        }

        // CFG-13: minimise PII sent to the external AI service - do NOT forward the user's name.
        // The bot only needs the message + a stable session id for conversation threading; it
        // types user_name as a required string, so send '' (the guest value) for everyone.
        $result = $bot->ask($data['message'], (int) $sid, '');

        return response()->json(['reply' => $result['reply']], $result['status']);
    }
}
