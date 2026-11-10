<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\BoardingAllocation;
use App\Models\BoardingHouse;
use App\Models\BoardingRoom;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\BoardingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/**
 * Boarding houses, their rooms, and the boarders in them. Follows the
 * reference system's hostel module.
 */
class BoardingController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'boarding';

    public function __construct(private BoardingService $boarding) {}

    /** @return array<int, \Illuminate\Routing\Controllers\Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('boarding.view', ['index', 'show', 'print']),
            static::can('boarding.manage', ['storeHouse', 'updateHouse', 'destroyHouse', 'storeRoom', 'updateRoom', 'destroyRoom']),
            static::can('boarding.allocate', ['allocate', 'checkOut']),
        ];
    }

    /** Every house at a glance, and the boarders who need doing something about. */
    public function index()
    {
        return view('admin.boarding.index', $this->boarding->overview() + ['staff' => $this->staff()]);
    }

    public function show(BoardingHouse $house)
    {
        $house->load(['rooms.occupants.student', 'houseMaster']);
        $boarders = $this->boarding->boarders();

        return view('admin.boarding.show', [
            'house' => $house,
            'boarders' => $boarders,
            'rooms' => $this->boarding->roomsWithSpace(),
            'staff' => $this->staff(),
        ]);
    }

    /** The house list, room by room, for the house master's wall and roll call. */
    public function print(BoardingHouse $house)
    {
        $house->load(['rooms.occupants.student', 'houseMaster']);

        return Pdf::loadView('admin.boarding.print', [
            'house' => $house,
            'boarders' => $this->boarding->boarders(),
            'school' => SchoolSetting::current(),
        ])->setPaper('a4', 'portrait')->download('boarding-'.\Illuminate\Support\Str::slug($house->name).'.pdf');
    }

    public function storeHouse(Request $request)
    {
        $house = BoardingHouse::create($this->validateHouse($request) + ['is_active' => true]);

        return redirect()->route('admin.boarding.show', $house)->with('success', __('House added. Now add its rooms.'));
    }

    public function updateHouse(Request $request, BoardingHouse $house)
    {
        $data = $this->validateHouse($request, $house) + ['is_active' => $request->boolean('is_active')];

        // Changing who a house takes must not leave boarders in a house that no longer takes them.
        $misplaced = $house->allocations()->whereNull('boarding_allocations.end_date')->with('student')->get()
            ->reject(fn ($a) => (new BoardingHouse(['gender' => $data['gender']]))->takes($a->student?->gender));
        if ($misplaced->isNotEmpty()) {
            return back()->withErrors(['gender' => trans_choice('{1} :count boarder in the house would no longer fit. Move them first.|[2,*] :count boarders in the house would no longer fit. Move them first.', $misplaced->count(), ['count' => $misplaced->count()])]);
        }

        $house->update($data);

        return back()->with('success', __('House updated.'));
    }

    public function destroyHouse(BoardingHouse $house)
    {
        if ($house->allocations()->exists()) {
            return back()->withErrors(['house' => __('This house has boarders in its history, so it can be closed but not deleted.')]);
        }

        $house->delete();

        return redirect()->route('admin.boarding.index')->with('success', __('House deleted.'));
    }

    public function storeRoom(Request $request, BoardingHouse $house)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('boarding_rooms')->where('boarding_house_id', $house->id)],
            'beds' => ['required', 'integer', 'min:1', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $house->rooms()->create($data + ['is_active' => true]);

        return back()->with('success', __('Room added.'));
    }

    public function updateRoom(Request $request, BoardingRoom $room)
    {
        $occupied = $room->occupants()->count();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('boarding_rooms')->where('boarding_house_id', $room->boarding_house_id)->ignore($room->id)],
            'beds' => ['required', 'integer', 'min:'.max(1, $occupied), 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['beds.min' => __('The room has :count boarders in it, so it needs at least :count beds.', ['count' => $occupied])]);

        $room->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', __('Room updated.'));
    }

    public function destroyRoom(BoardingRoom $room)
    {
        if ($room->allocations()->exists()) {
            return back()->withErrors(['room' => __('This room has boarders in its history, so it can be closed but not deleted.')]);
        }

        $room->delete();

        return back()->with('success', __('Room deleted.'));
    }

    /** Give a boarder a bed, or move them to another. */
    public function allocate(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'boarding_room_id' => ['required', 'integer', 'exists:boarding_rooms,id'],
            'bed' => ['nullable', 'string', 'max:20'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $room = BoardingRoom::with('house')->findOrFail($data['boarding_room_id']);

        $this->boarding->allocate($student, $room, $data['bed'] ?? null);

        return back()->with('success', __(':name is in :house, room :room.', ['name' => $student->full_name, 'house' => $room->house->name, 'room' => $room->name]));
    }

    public function checkOut(Request $request, BoardingAllocation $allocation)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->boarding->checkOut($allocation, $data['reason']);

        return back()->with('success', __(':name has left the bed.', ['name' => $allocation->student?->full_name]));
    }

    /** @return array<string, mixed> */
    private function validateHouse(Request $request, ?BoardingHouse $house = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('boarding_houses')->where('branch_id', $house?->branch_id ?? \App\Support\BranchContext::onlyBranch())->ignore($house?->id)],
            'gender' => ['required', Rule::in(BoardingHouse::GENDERS)],
            'house_master_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** Staff who could look after a house. */
    private function staff()
    {
        return User::query()->where('is_active', true)->whereNotIn('role', ['parent', 'student'])
            ->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
    }
}
