<?php

namespace App\Services;

use App\Models\Room;
use App\Models\TimetableEntry;
use App\Models\User;

/**
 * Finds every clash in a day's proposed timetable before it is saved.
 *
 * The screen checked one thing: whether a teacher was already booked in a
 * DIFFERENT class section. Three clashes went straight through:
 *
 *  - Two rows of the grid overlapping each other, which puts the class itself
 *    in two places at once.
 *  - The same teacher on two overlapping rows of the same grid — excluded by
 *    the `class_section_id != $sectionId` filter, so the one case the screen
 *    could most easily create was the one it never looked at.
 *  - Rooms, which were not checked at all, in either direction.
 *
 * Overlap is half-open — `start < otherEnd && end > otherStart` — so a period
 * ending at 10:00 and one starting at 10:00 do not clash.
 */
class TimetableConflictDetector
{
    /**
     * @param  array<int, array<string, mixed>>  $entries  the day's proposed rows
     * @return array<int, string> human-readable clashes; empty means clear
     */
    public function forDay(int $sessionId, int $sectionId, string $day, array $entries): array
    {
        $entries = array_values($entries);

        return array_merge(
            $this->withinTheGrid($entries),
            $this->againstOtherClasses($sessionId, $sectionId, $day, $entries),
        );
    }

    /**
     * Clashes between the submitted rows themselves.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function withinTheGrid(array $entries): array
    {
        $conflicts = [];

        foreach ($entries as $i => $entry) {
            foreach (array_slice($entries, $i + 1, null, true) as $j => $other) {
                if (! $this->overlaps($entry, $other)) {
                    continue;
                }

                $when = $this->window($entry).' / '.$this->window($other);

                // The class cannot be in two lessons at once, whoever teaches them.
                $conflicts[] = __('Periods :a and :b overlap (:when).', [
                    'a' => $i + 1,
                    'b' => $j + 1,
                    'when' => $when,
                ]);

                if ($this->same($entry, $other, 'teacher_id')) {
                    $conflicts[] = __(':teacher is booked twice over :when.', [
                        'teacher' => $this->teacherName($entry['teacher_id']),
                        'when' => $when,
                    ]);
                }

                if (! empty($entry['room_id']) && $this->same($entry, $other, 'room_id')) {
                    $conflicts[] = __('Room :room is booked twice over :when.', [
                        'room' => $this->roomName($entry['room_id']),
                        'when' => $when,
                    ]);
                }
            }
        }

        return $conflicts;
    }

    /**
     * Clashes with what other class sections already have saved.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function againstOtherClasses(int $sessionId, int $sectionId, string $day, array $entries): array
    {
        $conflicts = [];

        foreach ($entries as $entry) {
            $elsewhere = TimetableEntry::where('academic_session_id', $sessionId)
                ->where('day_of_week', $day)
                ->where('class_section_id', '!=', $sectionId)
                ->where('start_time', '<', $entry['end_time'])
                ->where('end_time', '>', $entry['start_time']);

            $teacherClash = (clone $elsewhere)
                ->where('teacher_id', $entry['teacher_id'])
                ->with('classSection')
                ->first();

            if ($teacherClash) {
                $conflicts[] = __(':teacher is already teaching :class on :day, :window.', [
                    'teacher' => $this->teacherName($entry['teacher_id']),
                    'class' => $teacherClash->classSection?->name ?? '?',
                    'day' => __(ucfirst($day)),
                    'window' => $this->window($teacherClash),
                ]);
            }

            if (empty($entry['room_id'])) {
                continue;
            }

            // Rooms were never checked here, so two classes could be scheduled
            // into the same room at the same time.
            $roomClash = (clone $elsewhere)
                ->where('room_id', $entry['room_id'])
                ->with('classSection')
                ->first();

            if ($roomClash) {
                $conflicts[] = __('Room :room is already taken by :class on :day, :window.', [
                    'room' => $this->roomName($entry['room_id']),
                    'class' => $roomClash->classSection?->name ?? '?',
                    'day' => __(ucfirst($day)),
                    'window' => $this->window($roomClash),
                ]);
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function overlaps(array $a, array $b): bool
    {
        return $a['start_time'] < $b['end_time'] && $a['end_time'] > $b['start_time'];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function same(array $a, array $b, string $key): bool
    {
        return ! empty($a[$key]) && (string) $a[$key] === (string) ($b[$key] ?? '');
    }

    /** @param array<string, mixed>|TimetableEntry $entry */
    private function window(array|TimetableEntry $entry): string
    {
        $start = is_array($entry) ? $entry['start_time'] : $entry->start_time;
        $end = is_array($entry) ? $entry['end_time'] : $entry->end_time;

        return substr((string) $start, 0, 5).'–'.substr((string) $end, 0, 5);
    }

    private function teacherName(int|string $id): string
    {
        return User::find($id)?->full_name ?? __('This teacher');
    }

    private function roomName(int|string $id): string
    {
        return Room::find($id)?->name ?? '#'.$id;
    }
}
