<?php

namespace App\Http\Controllers;

use App\Models\AiConfiguration;
use App\Models\ChatHistory;
use App\Services\QwenService;
use Illuminate\Http\Request;

class FridayController extends Controller
{
    public function index(Request $request)
    {
        $histories = $this->historyQuery($request)
            ->get()
            ->reverse()
            ->values();

        $configuration = AiConfiguration::current();
        $hasApiKey = (bool) $configuration?->isReady();

        return view('friday.index', compact('histories', 'hasApiKey'));
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

        return redirect()->route('friday.index');
    }

    public function history(Request $request)
    {
        $histories = $this->historyQuery($request)
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

    public function clear(Request $request)
    {
        // Always scope deletion to the authenticated account. An admin can only
        // clear the admin's own Friday history, never a user's history.
        $request->user()->chatHistories()->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Conversation history cleared.']);
        }

        return redirect()->route('friday.index')
            ->with('success', 'Conversation history cleared.');
    }

    private function historyQuery(Request $request)
    {
        return $request->user()
            ->chatHistories()
            ->latest('id')
            ->limit(100);
    }
}
