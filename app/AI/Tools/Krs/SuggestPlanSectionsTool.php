<?php

namespace App\AI\Tools\Krs;

use App\Models\KrsPlan;
use App\Models\User;
use App\Services\Krs\KrsScheduleGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SuggestPlanSectionsTool implements Tool
{
    public function __construct(
        protected KrsScheduleGenerator $generator,
        protected User $user,
    ) {}

    public function description(): Stringable|string
    {
        return 'Suggest alternative course sections for a KRS plan. Focus: conflict (fix overlaps), fill (add courses), optimize (general).';
    }

    public function handle(Request $request): Stringable|string
    {
        $args = $request->toArray();
        $planId = (int) ($args['plan_id'] ?? 0);
        $plan = KrsPlan::query()->find($planId);

        if (! $plan || ! $this->user->can('view', $plan)) {
            return json_encode(['error' => 'Rencana KRS tidak ditemukan atau tidak authorized.']);
        }

        $focus = (string) ($args['focus'] ?? 'optimize');
        $courseId = isset($args['course_id']) ? (int) $args['course_id'] : null;

        return json_encode(
            $this->generator->suggest($plan, $focus, $courseId),
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->description('KRS plan ID')->required(),
            'focus' => $schema->string()->description('conflict, fill, or optimize'),
            'course_id' => $schema->integer()->description('Optional course ID to limit suggestions'),
        ];
    }
}
