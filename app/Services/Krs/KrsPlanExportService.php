<?php

namespace App\Services\Krs;

use App\Models\KrsPlan;
use App\Support\GeneratedStamp;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class KrsPlanExportService
{
    public function toPdf(KrsPlan $plan): Response
    {
        $plan->load([
            'user',
            'courseOffering',
            'items.courseSection.schedules',
            'items.courseSection.course',
        ]);

        $items = $plan->items->sortBy(fn ($item) => $item->courseSection->course->code)->values();

        return Pdf::loadView('exports.krs-schedule', [
            'plan' => $plan,
            'items' => $items,
            'totalSks' => $plan->totalSks(),
            'generatedStamp' => GeneratedStamp::label(),
        ])->download("krs-{$plan->id}.pdf");
    }
}
