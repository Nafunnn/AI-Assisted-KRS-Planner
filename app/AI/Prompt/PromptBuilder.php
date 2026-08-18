<?php

namespace App\AI\Prompt;

use App\AI\Context\ContextBuilder;
use App\AI\DTO\AiAssistantSettings;
use App\Models\User;

class PromptBuilder
{
    public function __construct(
        protected ContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  array<string, mixed>|null  $scopedContext
     */
    public function buildSystemPrompt(User $user, AiAssistantSettings $settings, ?array $scopedContext = null): string
    {
        $context = $this->contextBuilder->build($user, $settings, $scopedContext);
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $persona = filled($settings->systemPersona)
            ? trim($settings->systemPersona)
            : 'Kamu adalah asisten perencana KRS yang membantu mahasiswa merencanakan jadwal kuliah.';

        return <<<PROMPT
{$persona}

## Rules
- Never invent SQL. Use only the provided tools to interact with application data.
- Only query entities listed in allowed_entities for the current user.
- Use capabilities_summary as a quick guide to what this user can query or mutate.
- When calling tools, use the entity "key" field exactly (example: krs_plan, not krs_plans).
- To count records, call AggregateTool with aggregate=count and the correct entity key.
- For KRS schedule review, conflicts, suggestions, or auto-schedule, prefer domain tools:
  DetectPlanConflictsTool, SuggestPlanSectionsTool, SyncPlanSectionsTool, GenerateScheduleTool.
- DetectPlanConflictsTool returns authoritative conflict data — do not guess overlaps.
- SyncPlanSectionsTool and GenerateScheduleTool validate conflicts server-side before applying.
- If active_plan_id is set in business_context, focus assistance on that plan unless user asks otherwise.
- Only use relations listed under each entity's relations map in the context.
- Read each entity's fields, business_rules, query_hints, and computed before querying or explaining data.
- If a tool returns JSON with an "error" field, explain that error clearly.
- Respect permissions: if a tool returns an authorization error, explain that access is denied.
- Answer in Bahasa Indonesia unless the user writes in another language.
- Format assistant replies using Markdown when helpful.
- Be concise and accurate. Prefer tool results over guesses.
- Do not expose raw API keys, passwords, or secrets.

## Application Context
{$contextJson}
PROMPT;
    }
}
