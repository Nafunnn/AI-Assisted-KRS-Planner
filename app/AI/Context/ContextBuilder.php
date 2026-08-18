<?php

namespace App\AI\Context;

use App\AI\DTO\AiAssistantSettings;
use App\AI\Registry\ModuleRegistry;
use App\AI\Services\PermissionGuard;
use App\Models\User;

class ContextBuilder
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected PermissionGuard $permissionGuard,
    ) {}

    /**
     * @param  array<string, mixed>|null  $scopedContext
     */
    public function build(User $user, AiAssistantSettings $settings, ?array $scopedContext = null): array
    {
        $entities = [];
        $allowedActions = [];

        foreach ($this->registry->all() as $key => $entity) {
            if (! $this->permissionGuard->canAccessDefinition($user, $entity)) {
                continue;
            }

            $allowedRelations = $this->permissionGuard->allowedRelationsForDefinition($user, $entity);
            $actions = $this->permissionGuard->allowedActionsForDefinition($user, $entity);

            $entities[$key] = array_merge($entity->toPromptArray($allowedRelations), [
                'admin_actions_available' => array_values(array_intersect($actions, ['list', 'show', 'update'])),
                'admin_location' => $entity->adminLocation,
            ]);
            $allowedActions[$key] = $actions;
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'allowed_entities' => array_keys($entities),
            'entities' => $entities,
            'allowed_actions' => $allowedActions,
            'capabilities_summary' => $this->permissionGuard->capabilitiesSummary($user),
            'business_context' => array_merge([
                'domain' => 'KRS Planner',
                'entity_hierarchy' => [
                    'course_offering' => 'Penawaran semester (root katalog)',
                    'course' => 'Mata kuliah dalam penawaran',
                    'course_section' => 'Kelompok/kelas spesifik per mata kuliah',
                    'section_schedule' => 'Slot jadwal per kelompok (hari + jam)',
                    'krs_plan' => 'Rencana KRS user untuk satu penawaran',
                    'krs_plan_item' => 'Kelompok terpilih dalam rencana',
                ],
                'rules' => [
                    'Satu kelompok per mata kuliah per rencana KRS.',
                    'Bentrok jadwal ditentukan oleh sistem (ScheduleConflictDetector), bukan AI.',
                    'SKS dihitung unik per kode mata kuliah.',
                    'Gunakan tool domain untuk review bentrok, saran kelompok, sync, dan generate jadwal.',
                    'Setiap entity menyertakan fields, business_rules, query_hints, dan computed untuk panduan query.',
                ],
            ], $scopedContext ?? []),
            'ai_enabled' => $settings->isEnabled,
            'provider' => $settings->provider,
        ];
    }
}
