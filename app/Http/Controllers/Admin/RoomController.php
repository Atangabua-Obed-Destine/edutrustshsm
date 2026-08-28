<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoomController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'classroom';

    public function index()
    {
        $rooms = Room::orderBy('name')->get();

        return view('admin.rooms.index', compact('rooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100', 'unique:rooms,name'],
            'building_floor' => ['nullable', 'string', 'max:150'],
            'capacity'       => ['nullable', 'integer', 'min:1', 'max:9999'],
            'type'           => ['nullable', 'string', 'max:100'],
        ]);

        $validated['is_active'] = true;

        Room::create($validated);

        return redirect()->route('admin.rooms.index')->with('success', 'Classroom added successfully.');
    }

    public function update(Request $request, Room $room)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100', 'unique:rooms,name,' . $room->id],
            'building_floor' => ['nullable', 'string', 'max:150'],
            'capacity'       => ['nullable', 'integer', 'min:1', 'max:9999'],
            'type'           => ['nullable', 'string', 'max:100'],
            'is_active'      => ['required', 'boolean'],
        ]);

        $room->update($validated);

        return redirect()->route('admin.rooms.index')->with('success', 'Classroom updated successfully.');
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', 'Classroom deleted.');
    }
}
