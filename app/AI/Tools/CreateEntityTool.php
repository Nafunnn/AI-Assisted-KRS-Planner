<?php

namespace App\AI\Tools;

use App\AI\DTO\CreateEntityDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityMutationService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateEntityTool implements Tool
{
    /**
     * @param  list<string>  $allowedKeys
     */
    public function __construct(
        protected EntityMutationService $mutationService,
        protected ModuleRegistry $registry,
        protected User $user,
        protected array $allowedKeys = [],
    ) {}

    public function description(): Stringable|string
    {
        $keys = $this->allowedKeys === []
            ? '(none — user has no create access)'
            : implode(', ', $this->allowedKeys);

        return "Create records in the application via approved entities. Allowed entity keys: {$keys}. Provide data payload.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $args = $request->toArray();

            $dto = CreateEntityDTO::fromArray([
                'entity' => (string) ($args['entity'] ?? ''),
                'data' => is_array($args['data'] ?? null) ? $args['data'] : [],
            ]);

            $result = $this->mutationService->create($this->user, $dto);

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Create failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key to create')->required(),
            'data' => $schema->object()->description('Data payload for creation')->required(),
        ];
    }
}
