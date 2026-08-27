<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;

class DashboardController extends Controller
{
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

        return view('admin.dashboard', compact('school', 'currentSession', 'stats'));
    }
}
