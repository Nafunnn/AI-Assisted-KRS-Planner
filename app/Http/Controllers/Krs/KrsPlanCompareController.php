<?php

namespace App\Http\Controllers\Krs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Krs\CompareKrsPlansRequest;
use App\Models\KrsPlan;
use App\Services\Krs\PlanComparisonService;
use Inertia\Inertia;
use Inertia\Response;

class KrsPlanCompareController extends Controller
{
    public function __invoke(CompareKrsPlansRequest $request, PlanComparisonService $comparisonService): Response
    {
        $planA = KrsPlan::query()->with(['user', 'courseOffering'])->findOrFail($request->integer('plan_a'));
        $planB = KrsPlan::query()->with(['user', 'courseOffering'])->findOrFail($request->integer('plan_b'));

        $this->authorize('view', $planA);
        $this->authorize('view', $planB);

        abort_unless(
            $planA->course_offering_id === $planB->course_offering_id,
            422,
            'Kedua rencana harus dari katalog semester yang sama.',
        );

        $comparison = $comparisonService->compare($planA, $planB);

        return Inertia::render('krs/Compare', [
            ...$comparison,
            'offering' => [
                'id' => $planA->courseOffering->id,
                'title' => $planA->courseOffering->title,
            ],
        ]);
    }
}
