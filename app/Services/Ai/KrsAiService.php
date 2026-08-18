<?php

namespace App\Services\Ai;

use App\Models\AiProviderConfig;
use App\Models\KrsPlan;
use App\Models\User;

class KrsAiService
{
    public function __construct(private AiProviderManager $providerManager) {}

    /**
     * @param  array<string, mixed>  $constraints
     * @return array{status: string, message: string}
     */
    public function autoSchedule(User $user, KrsPlan $plan, array $constraints): array
    {
        $config = $this->activeConfig($user);

        if ($config === null) {
            return [
                'status' => 'unavailable',
                'message' => 'Fitur auto-schedule membutuhkan konfigurasi AI aktif.',
            ];
        }

        return [
            'status' => 'planned',
            'message' => 'Auto-schedule akan memproses constraints: '.json_encode($constraints),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    public function reviewPlan(User $user, KrsPlan $plan): array
    {
        $config = $this->activeConfig($user);

        if ($config === null) {
            return [
                'status' => 'unavailable',
                'message' => 'Fitur review membutuhkan konfigurasi AI aktif.',
            ];
        }

        $plan->load(['items.courseSection.course', 'items.courseSection.schedules']);

        $summary = $plan->items->map(fn ($item) => [
            'code' => $item->courseSection->course->code,
            'name' => $item->courseSection->course->name,
            'group' => $item->courseSection->group_code,
            'schedules' => $item->courseSection->schedules->pluck('raw'),
        ])->values()->all();

        $driver = $this->providerManager->driver($config);

        try {
            $response = $driver->chat([
                [
                    'role' => 'system',
                    'content' => 'Kamu adalah asisten perencana KRS. Review jadwal mahasiswa dan berikan masukan singkat dalam Bahasa Indonesia.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Review jadwal KRS berikut: '.json_encode($summary),
                ],
            ]);
        } catch (\Throwable $exception) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => 'ok',
            'message' => $response->content,
        ];
    }

    private function activeConfig(User $user): ?AiProviderConfig
    {
        return $user->aiProviderConfigs()->where('is_active', true)->first();
    }
}
