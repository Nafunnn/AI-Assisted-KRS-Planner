<?php

namespace App\Http\Controllers\Krs;

use App\Http\Controllers\Controller;
use App\Models\KrsPlan;
use App\Services\Ai\KrsAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KrsAiController extends Controller
{
    public function autoSchedule(Request $request, KrsPlan $plan, KrsAiService $aiService): JsonResponse
    {
        $this->authorize('view', $plan);

        $result = $aiService->autoSchedule(
            $request->user(),
            $plan,
            $request->only(['free_days', 'max_end_time', 'min_sks', 'max_sks']),
        );

        return response()->json($result);
    }

    public function review(Request $request, KrsPlan $plan, KrsAiService $aiService): JsonResponse
    {
        $this->authorize('view', $plan);

        $result = $aiService->reviewPlan($request->user(), $plan);

        return response()->json($result);
    }
}
