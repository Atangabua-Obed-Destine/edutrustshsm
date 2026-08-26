<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ClassSection extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'form_id', 'section',
        'name', 'class_teacher_id', 'room_id', 'max_students', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function form() { return $this->belongsTo(Form::class); }
    public function stream() { return $this->belongsTo(Stream::class); }
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
