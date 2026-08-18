<?php

namespace App\Http\Controllers;

use App\AI\Chat\ChatService;
use App\AI\Memory\ConversationMemory;
use App\Http\Requests\Ai\SendAiChatRequest;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function send(SendAiChatRequest $request, ChatService $chatService): JsonResponse
    {
        $scopedContext = array_filter([
            'active_plan_id' => $request->integer('plan_id') ?: null,
            'active_offering_id' => $request->integer('offering_id') ?: null,
        ]);

        $result = $chatService->send(
            $request->user(),
            $request->string('message')->toString(),
            $request->string('conversation_id')->toString() ?: null,
            $scopedContext !== [] ? $scopedContext : null,
        );

        if ($result['status'] === 'unavailable') {
            return response()->json([
                'status' => 'unavailable',
                'message' => $result['reply'],
            ]);
        }

        /** @var AiConversation $conversation */
        $conversation = $result['conversation'];

        return response()->json([
            'status' => $result['status'],
            'reply' => $result['reply'],
            'conversation_id' => $conversation->id ?? null,
            'messages' => $conversation->relationLoaded('messages')
                ? $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                ])->values()
                : [],
        ]);
    }

    public function index(Request $request, ConversationMemory $memory): JsonResponse
    {
        return response()->json([
            'conversations' => $memory->listForUser($request->user())->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function destroy(Request $request, string $conversation, ConversationMemory $memory): JsonResponse
    {
        $deleted = $memory->delete($request->user(), $conversation);

        return response()->json(['deleted' => $deleted]);
    }
}
