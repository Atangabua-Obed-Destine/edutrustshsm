<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TeacherSubjectAssignment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'academic_session_id', 'user_id', 'subject_id', 'class_section_id',
    ];

    public function teacher() { return $this->belongsTo(User::class, 'user_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
}
