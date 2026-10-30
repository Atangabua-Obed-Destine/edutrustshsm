<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Event;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;

class DashboardController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'dashboard';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('dashboard.view', ['index']),
        ];
    }

    public function index()
    {
        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        $stats = [
            'total_students' => 0,
            'active_students' => 0,
            'total_teachers' => User::where('role', 'teacher')->where('is_active', true)->inBranchContext()->count(),
            'total_staff' => User::whereIn('role', ['staff', 'admin', 'accountant'])->where('is_active', true)->inBranchContext()->count(),
            'total_classes' => 0,
            'present_today' => 0,
            'absent_today' => 0,
        ];

        if ($currentSession) {
            $stats['total_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())->count();
            $stats['active_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)
                ->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())
                ->where('status', 'active')->count();
            $stats['total_classes'] = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->count();

            $todayAttendance = Attendance::whereDate('date', today())
                ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()));
            $stats['present_today'] = (clone $todayAttendance)->where('status', 'present')->count();
            $stats['absent_today'] = (clone $todayAttendance)->where('status', 'absent')->count();
        }

        // What the school has told its own staff. Without this a notice
        // addressed to staff could be written and filtered for, but never
        // actually read by anyone.
        $notices = Announcement::with('category')
            ->published()
            ->forStaff()
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        $events = Event::with('category')
            ->published()
            ->forStaff()
            ->upcoming()
            ->orderBy('starts_on')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('school', 'currentSession', 'stats', 'notices', 'events'));
    }
}
