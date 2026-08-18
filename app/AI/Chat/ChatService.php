<?php

namespace App\AI\Chat;

use App\AI\Agents\SystemArchitectAgent;
use App\AI\Memory\ConversationMemory;
use App\AI\Prompt\PromptBuilder;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\AiConfigResolver;
use App\AI\Services\EntityMutationService;
use App\AI\Services\EntityQueryService;
use App\AI\Services\PermissionGuard;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Krs\KrsPlanItemSyncer;
use App\Services\Krs\KrsScheduleGenerator;
use TypeError;

class ChatService
{
    public function __construct(
        protected AiConfigResolver $configResolver,
        protected ConversationMemory $memory,
        protected PromptBuilder $promptBuilder,
        protected EntityQueryService $queryService,
        protected ModuleRegistry $registry,
        protected EntityMutationService $mutationService,
        protected PermissionGuard $permissionGuard,
        protected KrsPlanItemSyncer $planItemSyncer,
        protected KrsScheduleGenerator $scheduleGenerator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $scopedContext
     * @return array{conversation: AiConversation, reply: string, status: string}
     */
    public function send(User $user, string $prompt, ?string $conversationId = null, ?array $scopedContext = null): array
    {
        if (! $this->permissionGuard->canUseAiAssistant($user)) {
            return [
                'conversation' => new AiConversation,
                'reply' => 'Fitur AI membutuhkan konfigurasi provider aktif. Atur di Settings → AI Providers.',
                'status' => 'unavailable',
            ];
        }

        $settings = $this->configResolver->assertEnabled($user);
        $options = $this->configResolver->promptOptions($user);

        $conversation = $conversationId
            ? $this->memory->findForUser($user, $conversationId)
            : null;

        if (! $conversation) {
            $conversation = $this->memory->create($user, scopedContext: $scopedContext);
        } elseif ($scopedContext !== null) {
            $conversation->update(['context' => $scopedContext]);
        }

        $this->memory->addMessage($conversation, 'user', $prompt);
        $this->memory->maybeSetTitleFromPrompt($conversation, $prompt);

        $history = $this->memory->toAgentMessages($conversation);
        array_pop($history);

        $agent = new SystemArchitectAgent(
            $user,
            $settings,
            $this->promptBuilder,
            $this->queryService,
            $this->registry,
            $this->mutationService,
            $this->permissionGuard,
            $this->planItemSyncer,
            $this->scheduleGenerator,
            $scopedContext ?? $conversation->context,
        );
        $agent->withHistory($history);

        try {
            $response = $agent->prompt(
                $prompt,
                provider: $options['provider'],
                model: $options['model'],
                timeout: $options['timeout'],
            );
        } catch (TypeError $e) {
            if (in_array($options['provider'], ['9router', 'custom-gateway'], true) && str_contains($e->getMessage(), 'validateTextResponse')) {
                throw new \RuntimeException(
                    'Gateway AI tidak mengembalikan respons chat yang valid. '
                    .'Pastikan 9Router berjalan di http://127.0.0.1:20128/v1.'
                );
            }

            throw $e;
        } catch (\Throwable $e) {
            $providerLabel = $this->configResolver->forUser($user)?->provider->label() ?? $options['provider'];

            $message = match (true) {
                str_contains($e->getMessage(), 'rate limited') => "Provider {$providerLabel} sedang rate limit. Tunggu sebentar, cek kuota di 9Router, atau ganti model/provider.",
                default => 'Permintaan AI gagal: '.$e->getMessage(),
            };

            return [
                'conversation' => $conversation->refresh(),
                'reply' => $message,
                'status' => 'error',
            ];
        }

        $reply = (string) ($response->text ?? '');

        $this->memory->addMessage($conversation, 'assistant', $reply, [
            'usage' => isset($response->usage) ? (array) $response->usage : null,
        ]);

        $conversation->refresh();
        $conversation->load('messages');

        return [
            'conversation' => $conversation,
            'reply' => $reply,
            'status' => 'ok',
        ];
    }
}
