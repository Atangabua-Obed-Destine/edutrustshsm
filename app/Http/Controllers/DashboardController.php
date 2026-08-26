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
            'total_teachers' => User::where('role', 'teacher')->where('is_active', true)->count(),
            'total_staff' => User::whereIn('role', ['staff', 'admin'])->where('is_active', true)->count(),
            'total_classes' => 0,
            'present_today' => 0,
            'absent_today' => 0,
        ];

        if ($currentSession) {
            $stats['total_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)->count();
            $stats['active_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)
                ->where('status', 'active')->count();
            $stats['total_classes'] = ClassSection::where('is_active', true)->count();

            $todayAttendance = Attendance::whereDate('date', today())
                ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id));
            $stats['present_today'] = (clone $todayAttendance)->where('status', 'present')->count();
            $stats['absent_today'] = (clone $todayAttendance)->where('status', 'absent')->count();
        }

        return view('admin.dashboard', compact('school', 'currentSession', 'stats'));
    }
}
