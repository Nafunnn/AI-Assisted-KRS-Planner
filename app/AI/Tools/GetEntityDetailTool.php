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

class GetEntityDetailTool implements Tool
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

        return "Fetch a single record by id with full detail and admin shortcuts (_links, _location). Available entities: {$keys}. Use after listing or when the user asks for detail/location of a specific record.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $args = $request->toArray();

            $with = $args['with'] ?? [];
            if (is_string($with)) {
                $decoded = json_decode($with, true);
                $with = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $with)));
            }

            $result = $this->queryService->findById(
                $this->user,
                (string) ($args['entity'] ?? ''),
                (string) ($args['id'] ?? ''),
                is_array($with) ? $with : [],
            );

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Detail lookup failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key (example: user)')->required(),
            'id' => $schema->string()->description('Record UUID from a prior query result')->required(),
            'with' => $schema->array()->description('Optional relation names to eager load')->items($schema->string()),
        ];
    }
}
