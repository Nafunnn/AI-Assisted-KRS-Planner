<?php

namespace App\Http\Controllers\Krs;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseOfferingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $offerings = CourseOffering::query()
            ->published()
            ->withCount('courses')
            ->with([
                'krsPlans' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->withCount('items')
                    ->orderBy('id'),
            ])
            ->latest('imported_at')
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'title' => $offering->title,
                'term' => $offering->term,
                'source_filename' => $offering->source_filename,
                'catalog_version' => $offering->catalog_version,
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

    /**
     * @return array<string, mixed>
     */
    public function transformOffering(CourseOffering $offering): array
    {
        return [
            'id' => $offering->id,
            'title' => $offering->title,
            'term' => $offering->term,
            'source_filename' => $offering->source_filename,
            'catalog_version' => $offering->catalog_version,
            'imported_at' => $offering->imported_at->toIso8601String(),
            'published_at' => $offering->published_at?->toIso8601String(),
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
                    'deprecated_at' => $section->deprecated_at?->toIso8601String(),
                    'schedules' => $section->schedules->map(fn ($schedule) => [
                        'id' => $schedule->id,
                        'slot_number' => $schedule->slot_number,
                        'day' => $schedule->day->value,
                        'day_label' => $schedule->day->label(),
                        'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                        'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                        'raw' => $schedule->raw,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
