<?php

namespace App\AI\Tools\Krs;

use App\Models\KrsPlan;
use App\Models\User;
use App\Services\Krs\KrsPlanItemSyncer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DetectPlanConflictsTool implements Tool
{
    public function __construct(
        protected KrsPlanItemSyncer $syncer,
        protected User $user,
    ) {}

    public function description(): Stringable|string
    {
        return 'Detect schedule conflicts and summarize the current KRS plan. Returns conflicts, SKS, and selected sections.';
    }

    public function handle(Request $request): Stringable|string
    {
        $planId = (int) ($request->toArray()['plan_id'] ?? 0);
        $plan = KrsPlan::query()->find($planId);

        if (! $plan || ! $this->user->can('view', $plan)) {
            return json_encode(['error' => 'Rencana KRS tidak ditemukan atau tidak authorized.']);
        }

        $plan->load(['items.courseSection.schedules', 'items.courseSection.course']);
        $sectionIds = $plan->items->pluck('course_section_id')->all();

        return json_encode(
            $this->syncer->summarize($plan, $sectionIds),
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->description('KRS plan ID')->required(),
        ];
    }
}
