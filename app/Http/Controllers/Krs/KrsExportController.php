<?php

namespace App\Http\Controllers\Krs;

use App\Http\Controllers\Controller;
use App\Models\KrsPlan;
use App\Services\Krs\KrsPlanExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KrsExportController extends Controller
{
    public function pdf(Request $request, KrsPlan $plan, KrsPlanExportService $exportService): Response
    {
        $this->authorize('view', $plan);

        return $exportService->toPdf($plan);
    }
}
