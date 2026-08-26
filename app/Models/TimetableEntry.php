<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'academic_session_id', 'class_section_id', 'subject_id', 'teacher_id',
        'room_id', 'day_of_week', 'start_time', 'end_time', 'timetable_slot_id',
    ];

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function room() { return $this->belongsTo(Room::class); }
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
}
