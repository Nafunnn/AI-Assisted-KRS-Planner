<?php

namespace App\AI\Agents;

use App\AI\DTO\AiAssistantSettings;
use App\AI\Prompt\PromptBuilder;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityMutationService;
use App\AI\Services\EntityQueryService;
use App\AI\Services\PermissionGuard;
use App\AI\Tools\AggregateTool;
use App\AI\Tools\CreateEntityTool;
use App\AI\Tools\DeleteEntityTool;
use App\AI\Tools\GetEntityDetailTool;
use App\AI\Tools\Krs\DetectPlanConflictsTool;
use App\AI\Tools\Krs\GenerateScheduleTool;
use App\AI\Tools\Krs\SuggestPlanSectionsTool;
use App\AI\Tools\Krs\SyncPlanSectionsTool;
use App\AI\Tools\QueryEntityTool;
use App\AI\Tools\SearchTool;
use App\AI\Tools\UpdateEntityTool;
use App\Models\User;
use App\Services\Krs\KrsPlanItemSyncer;
use App\Services\Krs\KrsScheduleGenerator;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class SystemArchitectAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /** @var list<Message> */
    protected array $history = [];

    /**
     * @param  array<string, mixed>|null  $scopedContext
     */
    public function __construct(
        protected User $user,
        protected AiAssistantSettings $settings,
        protected PromptBuilder $promptBuilder,
        protected EntityQueryService $queryService,
        protected ModuleRegistry $registry,
        protected EntityMutationService $mutationService,
        protected PermissionGuard $permissionGuard,
        protected KrsPlanItemSyncer $planItemSyncer,
        protected KrsScheduleGenerator $scheduleGenerator,
        protected ?array $scopedContext = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->promptBuilder->buildSystemPrompt($this->user, $this->settings, $this->scopedContext);
    }

    public function temperature(): float
    {
        return $this->settings->temperature;
    }

    public function maxTokens(): int
    {
        return $this->settings->maxTokens;
    }

    public function tools(): iterable
    {
        $allowedKeys = $this->permissionGuard->allowedEntityKeys($this->user);
        $mutationKeys = $this->permissionGuard->allowedMutationEntityKeys($this->user);
        $deleteKeys = $this->permissionGuard->allowedDeleteEntityKeys($this->user);

        $tools = [
            new QueryEntityTool($this->queryService, $this->registry, $this->user, $allowedKeys),
            new SearchTool($this->queryService, $this->registry, $this->user, $allowedKeys),
            new AggregateTool($this->queryService, $this->registry, $this->user, $allowedKeys),
            new GetEntityDetailTool($this->queryService, $this->registry, $this->user, $allowedKeys),
        ];

        if ($mutationKeys !== []) {
            $tools[] = new CreateEntityTool($this->mutationService, $this->registry, $this->user, $mutationKeys);
            $tools[] = new UpdateEntityTool($this->mutationService, $this->registry, $this->user, $mutationKeys);
        }

        if ($deleteKeys !== []) {
            $tools[] = new DeleteEntityTool($this->mutationService, $this->registry, $this->user, $deleteKeys);
        }

        if (in_array('krs_plan', $allowedKeys, true)) {
            $tools[] = new DetectPlanConflictsTool($this->planItemSyncer, $this->user);
            $tools[] = new SuggestPlanSectionsTool($this->scheduleGenerator, $this->user);
            $tools[] = new SyncPlanSectionsTool($this->planItemSyncer, $this->user);
            $tools[] = new GenerateScheduleTool($this->scheduleGenerator, $this->user);
        }

        return $tools;
    }

    /**
     * @param  list<Message>  $messages
     */
    public function withHistory(array $messages): static
    {
        $this->history = $messages;

        return $this;
    }

    public function messages(): iterable
    {
        return $this->history;
    }
}
