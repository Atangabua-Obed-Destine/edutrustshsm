<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\BoardingAllocation;
use App\Models\BoardingHouse;
use App\Models\BoardingRoom;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who sleeps where.
 *
 * Follows the reference system's hostel module, with what it leaves to the
 * house master's memory made into rules: a room holds no more boarders than
 * it has beds, a boys' house takes no girls, and only a pupil the school calls
 * a boarder this year is given a bed. Being a boarder is already on the
 * enrolment (it decides the boarding fee), so the house lists can say who
 * still needs a bed and who holds one they no longer should.
 */
class BoardingService
{
    /**
     * This year's boarders: pupils whose latest active enrolment in the
     * current year says they board. Half-boarders eat at school but go home.
     *
     * @return Collection<int, object{student_id: int, enrollment_id: int, class: ?string}>
     */
    public function boarders(): Collection
    {
        $session = AcademicSession::current();
        if (! $session) {
            return collect();
        }

        $latest = DB::table('student_enrollments')
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->groupBy('student_id')
            ->selectRaw('MAX(id) as id');

        return DB::table('student_enrollments as se')
            ->joinSub($latest, 'latest', 'latest.id', '=', 'se.id')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->leftJoin('class_sections as cs', 'cs.id', '=', 'se.class_section_id')
            ->where('se.residence_type', 'boarding')
            ->where('s.status', 'active')
            ->get(['se.student_id', 'se.id as enrollment_id', 'cs.name as class'])
            ->keyBy('student_id');
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $houses = BoardingHouse::with(['rooms.occupants', 'houseMaster'])->orderBy('name')->get();
        $boarders = $this->boarders();
        $current = BoardingAllocation::current()->with(['student', 'room.house'])->get();
        $placed = $current->pluck('student_id')->flip();

        $needBed = Student::whereIn('id', $boarders->keys()->reject(fn ($id) => $placed->has($id)))
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->each(fn ($s) => $s->setAttribute('class_name', $boarders[$s->id]->class ?? null));

        // Holding a bed but no longer a boarder: moved to day, left, or not
        // enrolled this year. The bed is not freed by itself, because the
        // house master should be the one to say the bed is empty.
        $shouldLeave = $current->reject(fn ($a) => $boarders->has($a->student_id))->values();

        $beds = $houses->flatMap->rooms->where('is_active', true)->sum('beds');
        $occupied = $current->count();

        return [
            'houses' => $houses,
            'needBed' => $needBed,
            'shouldLeave' => $shouldLeave,
            'boarders' => $boarders,
            'totals' => [
                'houses' => $houses->where('is_active', true)->count(),
                'beds' => $beds,
                'occupied' => $occupied,
                'free' => max(0, $beds - $occupied),
                'boarders' => $boarders->count(),
            ],
            'rooms' => $this->roomsWithSpace($houses),
        ];
    }

    /**
     * Rooms a boarder could go to, with their free beds.
     *
     * @return Collection<int, array{room: BoardingRoom, house: BoardingHouse, free: int}>
     */
    public function roomsWithSpace(?Collection $houses = null): Collection
    {
        $houses ??= BoardingHouse::with('rooms.occupants')->orderBy('name')->get();

        return $houses->where('is_active', true)->flatMap(fn (BoardingHouse $house) => $house->rooms
            ->where('is_active', true)
            ->map(fn (BoardingRoom $room) => ['room' => $room, 'house' => $house, 'free' => $room->beds - $room->occupants->count()])
            ->filter(fn ($r) => $r['free'] > 0))->values();
    }

    /**
     * Put a boarder in a bed. A boarder who already has one is moved: the old
     * bed is closed on the same day, so the history shows the move.
     */
    public function allocate(Student $student, BoardingRoom $room, ?string $bed = null, ?string $date = null): BoardingAllocation
    {
        $date ??= now()->toDateString();
        $bed = $bed !== null && trim($bed) !== '' ? trim($bed) : null;

        return DB::transaction(function () use ($student, $room, $bed, $date) {
            // Locked so that two people placing boarders at once cannot both
            // take the last bed.
            $room = BoardingRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $house = $room->house;

            $fail = fn (string $message) => throw ValidationException::withMessages(['room' => $message]);

            if (! $this->boarders()->has($student->id)) {
                $fail(__(':name is not a boarder this year. Change the residence on their enrolment to boarding first.', ['name' => $student->full_name]));
            }
            if (! $room->is_active || ! $house->is_active) {
                $fail(__('That room is closed.'));
            }
            if (! $house->takes($student->gender)) {
                $fail(__(':house does not take :name.', ['house' => $house->name, 'name' => $student->full_name]));
            }

            $existing = BoardingAllocation::current()->where('student_id', $student->id)->first();
            if ($existing && $existing->boarding_room_id === $room->id && $existing->bed === $bed) {
                return $existing;
            }

            $taken = BoardingAllocation::current()->where('boarding_room_id', $room->id)
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->get();
            if ($taken->count() >= $room->beds) {
                $fail(__('Room :room is full: :beds beds, :beds boarders.', ['room' => $room->name, 'beds' => $room->beds]));
            }
            if ($bed !== null && $taken->contains('bed', $bed)) {
                $fail(__('Bed :bed in room :room is already taken.', ['bed' => $bed, 'room' => $room->name]));
            }

            $existing?->update(['end_date' => $date, 'end_reason' => __('Moved to :house, room :room', ['house' => $house->name, 'room' => $room->name]), 'ended_by' => auth()->id()]);

            return BoardingAllocation::create([
                'student_id' => $student->id,
                'boarding_room_id' => $room->id,
                'bed' => $bed,
                'start_date' => $date,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /** The boarder leaves the bed. The record stays, closed. */
    public function checkOut(BoardingAllocation $allocation, string $reason, ?string $date = null): void
    {
        if ($allocation->end_date) {
            return;
        }

        $allocation->update(['end_date' => $date ?? now()->toDateString(), 'end_reason' => $reason, 'ended_by' => auth()->id()]);
    }

    /** A pupil who leaves the school leaves their bed with them. */
    public function release(Student $student, string $reason, string $date): void
    {
        BoardingAllocation::current()->where('student_id', $student->id)
            ->update(['end_date' => $date, 'end_reason' => $reason, 'ended_by' => auth()->id(), 'updated_at' => now()]);
    }
}
