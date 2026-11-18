<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiConfiguration;
use App\Models\ChatHistory;
use App\Services\QwenService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function index(Request $request)
    {
        // Only the current user's conversations are ever loaded.
        $histories = $request->user()
            ->chatHistories()
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse();

        $hasApiKey = (bool) AiConfiguration::current()?->isReady();

        return view('user.ai-assistant', compact('histories', 'hasApiKey'));
    }

    public function send(Request $request, QwenService $qwen)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = trim($validated['message']);
        $user = $request->user();

        $reply = $qwen->reply($user, $message);

        $chat = $user->chatHistories()->create([
            'message' => $message,
            'response' => $reply,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $chat->id,
                'message' => $chat->message,
                'response' => $chat->response,
                'time' => $chat->created_at->format('H:i'),
            ]);
        }

        return redirect()->route('user.ai.index');
    }

    public function clear(Request $request)
    {
        $request->user()->chatHistories()->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Conversation history cleared.']);
        }

        return redirect()->route('user.ai.index')
            ->with('success', 'Conversation history cleared.');
    }

    /**
     * Recent conversation for the floating widget. Only the authenticated
     * user's own history is returned, and the payload is always scoped.
     */
    public function history(Request $request)
    {
        $histories = $request->user()
            ->chatHistories()
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatHistory $chat) => [
                'id' => $chat->id,
                'message' => $chat->message,
                'response' => $chat->response,
                'time' => $chat->created_at->format('H:i'),
            ]);

        return response()->json(['histories' => $histories]);
    }
}
