<?php

namespace App\Http\Controllers\Krs\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Krs\CourseOfferingController as StudentCourseOfferingController;
use App\Http\Requests\Krs\ImportCourseOfferingRequest;
use App\Http\Requests\Krs\SyncCourseOfferingRequest;
use App\Models\CourseOffering;
use App\Services\Krs\CourseOfferingImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CourseOfferingController extends Controller
{
    public function __construct(
        private StudentCourseOfferingController $studentOfferingController,
    ) {}

    public function index(): Response
    {
        $this->authorize('create', CourseOffering::class);

        $offerings = CourseOffering::query()
            ->withCount(['courses', 'krsPlans'])
            ->latest('imported_at')
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'title' => $offering->title,
                'term' => $offering->term,
                'source_filename' => $offering->source_filename,
                'catalog_version' => $offering->catalog_version,
                'imported_at' => $offering->imported_at->toIso8601String(),
                'published_at' => $offering->published_at?->toIso8601String(),
                'courses_count' => $offering->courses_count,
                'plans_count' => $offering->krs_plans_count,
            ]);

        return Inertia::render('krs/admin/Offerings', [
            'offerings' => $offerings,
        ]);
    }

    public function store(ImportCourseOfferingRequest $request, CourseOfferingImportService $importService): RedirectResponse
    {
        $this->authorize('create', CourseOffering::class);

        try {
            $result = $importService->create(
                $request->user(),
                $request->file('file'),
                $request->string('title')->toString() ?: null,
                $request->string('term')->toString() ?: null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $message = "Berhasil mengimpor {$result['courses_count']} mata kuliah ({$result['sections_count']} kelompok).";

        if ($result['errors'] !== []) {
            $message .= ' Beberapa baris dilewati karena error.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('krs.admin.offerings.index');
    }

    public function previewSync(
        SyncCourseOfferingRequest $request,
        CourseOffering $offering,
        CourseOfferingImportService $importService,
    ): RedirectResponse {
        $this->authorize('sync', $offering);

        try {
            $result = $importService->sync(
                $request->user(),
                $offering,
                $request->file('file'),
                dryRun: true,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('sync_preview', [
            'offering_id' => $offering->id,
            'courses_created' => $result['courses_created'],
            'courses_updated' => $result['courses_updated'],
            'sections_created' => $result['sections_created'],
            'sections_updated' => $result['sections_updated'],
            'sections_deprecated' => $result['sections_deprecated'],
            'schedule_changed_sections' => $result['schedule_changed_sections'],
            'affected_plan_items' => $result['affected_plan_items'],
            'affected_plans_count' => $result['affected_plans_count'],
            'errors_count' => count($result['errors']),
        ]);
    }

    public function sync(
        SyncCourseOfferingRequest $request,
        CourseOffering $offering,
        CourseOfferingImportService $importService,
    ): RedirectResponse {
        $this->authorize('sync', $offering);

        try {
            $result = $importService->sync(
                $request->user(),
                $offering,
                $request->file('file'),
                dryRun: false,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $message = "Katalog diperbarui ke versi {$result['offering']->catalog_version}. "
            ."{$result['affected_plans_count']} rencana terdampak.";

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('krs.admin.offerings.index');
    }

    public function unpublish(CourseOffering $offering): RedirectResponse
    {
        $this->authorize('update', $offering);

        $offering->update(['published_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Katalog di-unpublish.']);

        return redirect()->route('krs.admin.offerings.index');
    }

    public function publish(CourseOffering $offering): RedirectResponse
    {
        $this->authorize('update', $offering);

        $offering->update(['published_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Katalog dipublish.']);

        return redirect()->route('krs.admin.offerings.index');
    }

    public function destroy(CourseOffering $offering): RedirectResponse
    {
        $this->authorize('delete', $offering);

        if ($offering->krsPlans()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Katalog masih punya rencana mahasiswa. Unpublish saja, atau hapus rencana terlebih dahulu.',
            ]);

            return back();
        }

        $offering->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Katalog dihapus.']);

        return redirect()->route('krs.admin.offerings.index');
    }

    public function show(CourseOffering $offering): Response
    {
        $this->authorize('view', $offering);

        $offering->load(['courses.sections.schedules']);

        return Inertia::render('krs/OfferingShow', [
            'offering' => $this->studentOfferingController->transformOffering($offering),
        ]);
    }
}
