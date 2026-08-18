<?php

namespace App\AI\Tools\Krs;

use App\Models\KrsPlan;
use App\Models\User;
use App\Services\Krs\KrsPlanItemSyncer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SyncPlanSectionsTool implements Tool
{
    public function __construct(
        protected KrsPlanItemSyncer $syncer,
        protected User $user,
    ) {}

    public function description(): Stringable|string
    {
        return 'Apply a list of course section IDs to a KRS plan. Validates conflicts server-side. Set apply=true to persist.';
    }

    public function handle(Request $request): Stringable|string
    {
        $args = $request->toArray();
        $planId = (int) ($args['plan_id'] ?? 0);
        $plan = KrsPlan::query()->find($planId);

        if (! $plan || ! $this->user->can('update', $plan)) {
            return json_encode(['error' => 'Rencana KRS tidak ditemukan atau tidak authorized.']);
        }

        $sectionIds = $args['section_ids'] ?? [];

        if (is_string($sectionIds)) {
            $decoded = json_decode($sectionIds, true);
            $sectionIds = is_array($decoded) ? $decoded : [];
        }

        $sectionIds = array_map('intval', array_values($sectionIds));
        $mode = (string) ($args['mode'] ?? 'replace');
        $apply = (bool) ($args['apply'] ?? false);

        return json_encode(
            $this->syncer->sync($this->user, $plan, $sectionIds, $mode, $apply),
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->description('KRS plan ID')->required(),
            'section_ids' => $schema->array()->description('Course section IDs to apply')->items($schema->integer())->required(),
            'mode' => $schema->string()->description('replace or fill'),
            'apply' => $schema->boolean()->description('Persist changes when true'),
        ];
    }
}
