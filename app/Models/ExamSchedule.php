<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'academic_session_id', 'class_section_id', 'subject_id', 'room_id',
        'exam_date', 'start_time', 'end_time',
    ];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function room() { return $this->belongsTo(Room::class); }
}
