<?php

namespace App\AI\Tools\Krs;

use App\Models\KrsPlan;
use App\Models\User;
use App\Services\Krs\KrsScheduleGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateScheduleTool implements Tool
{
    public function __construct(
        protected KrsScheduleGenerator $generator,
        protected User $user,
    ) {}

    public function description(): Stringable|string
    {
        return 'Generate an automatic KRS schedule using deterministic Laravel logic. Supports min_sks, max_sks, free_days, max_end_time constraints.';
    }

    public function handle(Request $request): Stringable|string
    {
        $args = $request->toArray();
        $planId = (int) ($args['plan_id'] ?? 0);
        $plan = KrsPlan::query()->find($planId);

        if (! $plan || ! $this->user->can('update', $plan)) {
            return json_encode(['error' => 'Rencana KRS tidak ditemukan atau tidak authorized.']);
        }

        $constraints = is_array($args['constraints'] ?? null) ? $args['constraints'] : [];
        $apply = (bool) ($args['apply'] ?? false);

        if (isset($args['min_sks'])) {
            $constraints['min_sks'] = (int) $args['min_sks'];
        }

        if (isset($args['max_sks'])) {
            $constraints['max_sks'] = (int) $args['max_sks'];
        }

        if (isset($args['free_days'])) {
            $constraints['free_days'] = $args['free_days'];
        }

        if (isset($args['max_end_time'])) {
            $constraints['max_end_time'] = $args['max_end_time'];
        }

        if (isset($args['mode'])) {
            $constraints['mode'] = $args['mode'];
        }

        return json_encode(
            $this->generator->generate($plan, $constraints, $apply),
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->description('KRS plan ID')->required(),
            'min_sks' => $schema->integer()->description('Minimum SKS target'),
            'max_sks' => $schema->integer()->description('Maximum SKS target'),
            'free_days' => $schema->array()->description('Days to keep free (senin, selasa, ...)')->items($schema->string()),
            'max_end_time' => $schema->string()->description('Latest end time HH:MM'),
            'mode' => $schema->string()->description('replace or fill'),
            'apply' => $schema->boolean()->description('Persist when true'),
        ];
    }
}
