<?php

namespace App\Http\Controllers\Krs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Krs\ImportCourseOfferingRequest;
use App\Models\CourseOffering;
use App\Services\Krs\CourseOfferingImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CourseOfferingController extends Controller
{
    public function index(Request $request): Response
    {
        $offerings = $request->user()
            ->courseOfferings()
            ->withCount('courses')
            ->with(['krsPlans' => fn ($query) => $query->withCount('items')->orderBy('id')])
            ->latest('imported_at')
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'title' => $offering->title,
                'source_filename' => $offering->source_filename,
                'imported_at' => $offering->imported_at->toIso8601String(),
                'courses_count' => $offering->courses_count,
                'plans' => $offering->krsPlans->map(fn ($plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'items_count' => $plan->items_count,
                ])->values(),
            ]);

        return Inertia::render('krs/Index', [
            'offerings' => $offerings,
        ]);
    }

    public function store(ImportCourseOfferingRequest $request, CourseOfferingImportService $importService): RedirectResponse
    {
        try {
            $result = $importService->import(
                $request->user(),
                $request->file('file'),
                $request->string('title')->toString() ?: null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $message = "Berhasil mengimpor {$result['courses_count']} mata kuliah ({$result['sections_count']} kelompok).";

        if ($result['errors'] !== []) {
            $message .= ' Beberapa baris dilewati karena error.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        $offering = $result['offering']->load('latestPlan');

        abort_unless($offering->latestPlan !== null, 500);

        return redirect()->route('krs.planner', [$offering, $offering->latestPlan]);
    }

    public function show(Request $request, CourseOffering $offering): Response
    {
        $this->authorize('view', $offering);

        $offering->load([
            'courses.sections.schedules',
        ]);

        return Inertia::render('krs/OfferingShow', [
            'offering' => $this->transformOffering($offering),
        ]);
    }

    public function destroy(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->authorize('delete', $offering);

        $offering->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penawaran mata kuliah dihapus.']);

        return redirect()->route('krs.index');
    }

    /**
     * @return array<string, mixed>
     */
    public function transformOffering(CourseOffering $offering): array
    {
        return [
            'id' => $offering->id,
            'title' => $offering->title,
            'source_filename' => $offering->source_filename,
            'imported_at' => $offering->imported_at->toIso8601String(),
            'courses' => $offering->courses->map(fn ($course) => [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'sks' => $course->sks,
                'class_type' => $course->class_type->value,
                'class_type_label' => $course->class_type->label(),
                'sections' => $course->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'group_code' => $section->group_code,
                    'time_period' => $section->time_period->value,
                    'time_period_label' => $section->time_period->label(),
                    'schedules' => $section->schedules->map(fn ($schedule) => [
                        'id' => $schedule->id,
                        'slot_number' => $schedule->slot_number,
                        'day' => $schedule->day->value,
                        'day_label' => $schedule->day->label(),
                        'starts_at' => substr($schedule->starts_at, 0, 5),
                        'ends_at' => substr($schedule->ends_at, 0, 5),
                        'raw' => $schedule->raw,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
