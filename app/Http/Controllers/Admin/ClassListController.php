<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use App\Services\TeachingScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The class list (liste nominative): every pupil in a class with matricule,
 * sex, date of birth, repeater flag and a parent to call, boys and girls
 * counted. Printed for registers, inspections and the ministry's returns.
 */
class ClassListController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [static::can('student.view', ['index', 'csv'])];
    }

    public function index(Request $request)
    {
        $sections = $this->sections();
        $section = $this->chosen($request, $sections);

        return view('admin.students.class-list', [
            'sections' => $sections,
            'section' => $section,
            'rows' => $section ? $this->rows($section) : collect(),
            'school' => SchoolSetting::current(),
            'session' => AcademicSession::current(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $section = $this->chosen($request, $this->sections());
        abort_unless($section, 404);
        $rows = $this->rows($section);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('No.'), __('Matricule'), __('Name'), __('Sex'), __('Date of Birth'), __('Place of Birth'), __('Repeater'), __('Parent / Guardian'), __('Phone')]);
            foreach ($rows as $i => $r) {
                fputcsv($out, [$i + 1, $r['matricule'], $r['name'], $r['sex'], $r['dob'], $r['pob'], $r['repeater'] ? __('Yes') : __('No'), $r['parent'], $r['phone']]);
            }
            fclose($out);
        }, 'class-list-'.str($section->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function sections(): Collection
    {
        $query = ClassSection::with('form')->where('is_active', true)
            ->whereHas('form', fn ($f) => $f->forCurrentLevel());

        return app(TeachingScope::class)->scopeSections($query, auth()->user())->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])->values();
    }

    /** The class asked for, if this user may see it; a teacher with one class gets it straight away. */
    private function chosen(Request $request, Collection $sections): ?ClassSection
    {
        $id = $request->integer('class_section_id');

        return $id ? $sections->firstWhere('id', $id) : ($sections->count() === 1 ? $sections->first() : null);
    }

    private function rows(ClassSection $section): Collection
    {
        $session = AcademicSession::current();

        $enrollments = StudentEnrollment::with('student.guardian')
            ->where('academic_session_id', $session?->id)
            ->where('class_section_id', $section->id)
            ->where('status', 'active')
            ->get();

        // A repeater sat in this same level in an earlier year.
        $repeaters = StudentEnrollment::whereIn('student_id', $enrollments->pluck('student_id'))
            ->where('academic_session_id', '!=', $session?->id)
            ->whereHas('classSection', fn ($q) => $q->where('form_id', $section->form_id))
            ->pluck('student_id')->unique()->flip();

        return $enrollments->map(function ($e) use ($repeaters) {
            $s = $e->student;
            $g = $s?->guardian;

            return [
                'matricule' => $s?->student_id,
                'name' => trim(($s?->last_name ?? '').' '.($s?->first_name ?? '').' '.($s?->other_names ?? '')),
                'last' => $s?->last_name, 'first' => $s?->first_name,
                'sex' => match ($s?->gender) { 'male' => __('M'), 'female' => __('F'), default => '—' },
                'gender' => $s?->gender,
                'dob' => $s?->date_of_birth?->format('d/m/Y'),
                'age' => $s?->date_of_birth ? (int) floor($s->date_of_birth->diffInYears(now())) : null,
                'pob' => $s?->place_of_birth,
                'repeater' => isset($repeaters[$e->student_id]),
                'parent' => $g?->guardian_name ?: $g?->father_name ?: $g?->mother_name ?: $g?->emergency_contact_name,
                'phone' => $g?->guardian_phone ?: $g?->father_phone ?: $g?->mother_phone ?: $g?->emergency_contact_phone,
            ];
        })->sortBy(fn ($r) => [mb_strtolower($r['last'] ?? ''), mb_strtolower($r['first'] ?? '')])->values();
    }
}
