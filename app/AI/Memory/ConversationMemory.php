<?php

namespace App\AI\Memory;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\Message;

class ConversationMemory
{
    public function listForUser(User $user, int $limit = 30): Collection
    {
        return AiConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function findForUser(User $user, string $conversationId): ?AiConversation
    {
        return AiConversation::query()
            ->where('user_id', $user->id)
            ->where('id', $conversationId)
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $scopedContext
     */
    public function create(User $user, ?string $title = null, ?array $scopedContext = null): AiConversation
    {
        return AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => $title ?: config('ai-platform.conversation.default_title', 'Percakapan baru'),
            'context' => $scopedContext,
        ]);
    }

    public function addMessage(AiConversation $conversation, string $role, string $content, ?array $toolMeta = null): AiMessage
    {
        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'tool_meta' => $toolMeta,
        ]);

        $conversation->touch();

        return $message;
    }

    /**
     * @return list<Message>
     */
    public function toAgentMessages(AiConversation $conversation): array
    {
        $max = (int) config('ai-platform.conversation.max_messages', 50);

        return $conversation->messages()
            ->orderByDesc('created_at')
            ->limit($max)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message) => new Message($message->role, $message->content))
            ->all();
    }

    public function maybeSetTitleFromPrompt(AiConversation $conversation, string $prompt): void
    {
        $default = config('ai-platform.conversation.default_title', 'Percakapan baru');

        if ($conversation->title !== $default) {
            return;
        }

        $conversation->update([
            'title' => mb_substr(trim($prompt), 0, 80) ?: $default,
        ]);
    }

    public function delete(User $user, string $conversationId): bool
    {
        $conversation = $this->findForUser($user, $conversationId);

        if (! $conversation) {
            return false;
        }

        return (bool) $conversation->delete();
    }
}
