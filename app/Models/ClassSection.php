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
        'form_id', 'stream_id', 'section',
        'name', 'class_teacher_id', 'room_id', 'max_students', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // NOTE: class_sections has no academic_session_id — sections belong to a
    // Form and are reused across sessions. stream_id is optional: set, the
    // class is that stream (Lower Sixth Science) and every pupil placed in it
    // takes that stream (StudentEnrollment::booted); empty, each pupil's
    // stream is chosen on their own.
    public function councils()
    {
        return $this->hasMany(ClassCouncil::class);
    }

    public function form() { return $this->belongsTo(Form::class); }
    public function stream() { return $this->belongsTo(Stream::class); }

    /**
     * The subjects taught in this class: the form's subjects, narrowed to the
     * class's stream when it has one.
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string, code: string}>
     */
    public function curriculum(): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Facades\DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $this->form_id)
            ->where('subjects.is_active', true)
            ->when($this->stream_id, fn ($q) => $q->where(fn ($w) => $w->where('form_subject.stream_id', $this->stream_id)->orWhereNull('form_subject.stream_id')))
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name', 'subjects.code'])
            ->unique('id')
            ->values();
    }
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
