<?php

namespace App\AI\Tools;

use App\AI\DTO\AggregateDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityQueryService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AggregateTool implements Tool
{
    /**
     * @param  list<string>  $allowedKeys
     */
    public function __construct(
        protected EntityQueryService $queryService,
        protected ModuleRegistry $registry,
        protected User $user,
        protected array $allowedKeys = [],
    ) {}

    public function description(): Stringable|string
    {
        $keys = $this->allowedKeys === []
            ? '(none — user has no query access)'
            : implode(', ', $this->allowedKeys);

        return "Aggregate entity data (count, sum, avg, min, max). Use exact entity keys: {$keys}. Example for user count: entity=user, aggregate=count.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $args = $request->toArray();

            $filters = $args['filters'] ?? [];
            if (is_string($filters)) {
                $decoded = json_decode($filters, true);
                $filters = is_array($decoded) ? $decoded : [];
            }

            $dto = AggregateDTO::fromArray([
                'entity' => (string) ($args['entity'] ?? ''),
                'aggregate' => (string) ($args['aggregate'] ?? 'count'),
                'field' => $args['field'] ?? null,
                'filters' => is_array($filters) ? $filters : [],
                'group_by' => $args['group_by'] ?? null,
            ]);

            $result = $this->queryService->aggregate($this->user, $dto);

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Aggregate failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key')->required(),
            'aggregate' => $schema->string()->description('count, sum, avg, min, or max')->required(),
            'field' => $schema->string()->description('Field for sum/avg/min/max'),
            'filters' => $schema->object()->description('Optional filters'),
            'group_by' => $schema->string()->description('Optional group-by field'),
        ];
    }
}
