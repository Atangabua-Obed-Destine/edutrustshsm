<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ClassSection extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'form_id', 'section',
        'name', 'class_teacher_id', 'room_id', 'max_students', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // NOTE: class_sections has no academic_session_id and no stream_id — sections
    // belong to a Form and are reused across sessions. The academicSession() and
    // stream() relations that used to live here referenced columns that do not
    // exist; they crashed on use (Promotion, Bulk Upload) and silently returned
    // null elsewhere (StudentController wiped enrollment streams with them).
    public function form() { return $this->belongsTo(Form::class); }
    public function classTeacher() { return $this->belongsTo(User::class, 'class_teacher_id'); }
    public function room() { return $this->belongsTo(Room::class); }
    public function studentEnrollments() { return $this->hasMany(StudentEnrollment::class); }
    public function activeStudents() { return $this->studentEnrollments()->where('status', 'active'); }
    public function teacherAssignments() { return $this->hasMany(TeacherSubjectAssignment::class); }

    public function getStudentCountAttribute(): int
    {
        return $this->activeStudents()->count();
    }
}
