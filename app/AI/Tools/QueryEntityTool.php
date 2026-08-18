<?php

namespace App\AI\Tools;

use App\AI\DTO\EntityQueryDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityQueryService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryEntityTool implements Tool
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

        return "Query application entities using Eloquent filters. Available entities: {$keys}. Never invent SQL.";
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

            $with = $args['with'] ?? [];
            if (is_string($with)) {
                $decoded = json_decode($with, true);
                $with = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $with)));
            }

            $dto = EntityQueryDTO::fromArray([
                'entity' => (string) ($args['entity'] ?? ''),
                'filters' => is_array($filters) ? $filters : [],
                'search' => isset($args['search']) ? (string) $args['search'] : null,
                'sort_by' => $args['sort_by'] ?? null,
                'sort_dir' => $args['sort_dir'] ?? 'asc',
                'limit' => $args['limit'] ?? null,
                'offset' => $args['offset'] ?? 0,
                'with' => is_array($with) ? $with : [],
            ]);

            $result = $this->queryService->query($this->user, $dto);

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Query failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key to query')->required(),
            'filters' => $schema->object()->description('Key-value filters limited to filterable fields'),
            'search' => $schema->string()->description('Optional free-text search across searchable fields'),
            'sort_by' => $schema->string()->description('Sortable field name'),
            'sort_dir' => $schema->string()->description('asc or desc'),
            'limit' => $schema->integer()->description('Max rows to return'),
            'offset' => $schema->integer()->description('Offset for pagination'),
            'with' => $schema->array()->description('Relation names to eager load')->items($schema->string()),
        ];
    }
}
