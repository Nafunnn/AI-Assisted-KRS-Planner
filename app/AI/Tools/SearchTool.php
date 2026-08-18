<?php

namespace App\AI\Tools;

use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityQueryService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchTool implements Tool
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

        return "Search entities by text across searchable fields. Available entities: {$keys}.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $args = $request->toArray();

            $result = $this->queryService->search(
                $this->user,
                (string) ($args['entity'] ?? ''),
                (string) ($args['query'] ?? ''),
                (int) ($args['limit'] ?? 20),
            );

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Search failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key to search')->required(),
            'query' => $schema->string()->description('Search text')->required(),
            'limit' => $schema->integer()->description('Max rows'),
        ];
    }
}
