<?php

namespace App\AI\Tools;

use App\AI\DTO\UpdateEntityDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\EntityMutationService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateEntityTool implements Tool
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
            ? '(none — user has no update access)'
            : implode(', ', $this->allowedKeys);

        return "Update records via approved entities. Allowed entity keys: {$keys}. Provide id and data payload.";
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $args = $request->toArray();

            $dto = UpdateEntityDTO::fromArray([
                'entity' => (string) ($args['entity'] ?? ''),
                'id' => (string) ($args['id'] ?? ''),
                'data' => is_array($args['data'] ?? null) ? $args['data'] : [],
            ]);

            $result = $this->mutationService->update($this->user, $dto);

            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (UnauthorizedEntityAccessException|EntityNotFoundException $e) {
            return json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Update failed: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Entity key to update')->required(),
            'id' => $schema->string()->description('UUID id of the record')->required(),
            'data' => $schema->object()->description('Data payload for update')->required(),
        ];
    }
}
