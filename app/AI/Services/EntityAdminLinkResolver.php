<?php

namespace App\AI\Services;

use App\AI\Registry\EntityDefinition;
use App\Models\User;

class EntityAdminLinkResolver
{
    /** @var array<string, string> */
    protected const ACTION_PERMISSIONS = [
        'list' => 'list',
        'show' => 'show',
        'edit' => 'update',
    ];

    /** @var array<string, string> */
    protected const DEFAULT_LABELS = [
        'list' => 'Daftar',
        'show' => 'Lihat Detail',
        'edit' => 'Edit',
    ];

    public function __construct(
        protected PermissionGuard $permissionGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, array{url: string, label: string}>
     */
    public function linksForRecord(User $user, EntityDefinition $entity, array $record): array
    {
        if ($entity->adminRoutes === []) {
            return [];
        }

        $links = [];

        foreach ($entity->adminRoutes as $action => $routeDef) {
            if (! is_array($routeDef) || ! isset($routeDef['route'])) {
                continue;
            }

            $permission = self::ACTION_PERMISSIONS[$action] ?? $action;

            if (! $this->permissionGuard->canAccessDefinition($user, $entity, $permission)) {
                continue;
            }

            $url = $this->resolveUrl($routeDef, $record);

            if ($url === null) {
                continue;
            }

            $links[$action] = [
                'url' => $url,
                'label' => $routeDef['label'] ?? self::DEFAULT_LABELS[$action] ?? ucfirst($action),
            ];
        }

        return $links;
    }

    public function location(EntityDefinition $entity, string $action = 'show'): ?string
    {
        return $entity->adminLocation[$action]
            ?? $entity->adminLocation['show']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function enrichRecord(User $user, EntityDefinition $entity, array $record): array
    {
        $links = $this->linksForRecord($user, $entity, $record);

        if ($links !== []) {
            $record['_links'] = $links;
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function enrichMutationResponse(User $user, EntityDefinition $entity, array $response): array
    {
        if (isset($response['error'])) {
            return $response;
        }

        $links = $this->linksForRecord($user, $entity, $response);

        if ($links !== []) {
            $response['_links'] = $links;
        }

        $locationAction = isset($response['deleted']) ? 'list' : 'show';
        $location = $this->location($entity, $locationAction);

        if ($location !== null) {
            $response['_location'] = $location;
        }

        return $response;
    }

    /**
     * @param  array{route: string, param?: string, label?: string}  $routeDef
     * @param  array<string, mixed>  $record
     */
    protected function resolveUrl(array $routeDef, array $record): ?string
    {
        try {
            if (! isset($routeDef['param'])) {
                return route($routeDef['route']);
            }

            $param = $routeDef['param'];
            $value = $record[$param] ?? null;

            if (! filled($value)) {
                return null;
            }

            return route($routeDef['route'], $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
