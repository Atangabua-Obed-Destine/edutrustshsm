<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LevelContext;
use Illuminate\Http\Request;

class LevelContextController extends Controller
{
    /**
     * Switch the admin's active school-level working context.
     */
    public function switch(Request $request)
    {
        $validated = $request->validate([
            'school_level' => ['required', 'in:' . implode(',', LevelContext::options())],
        ]);

        LevelContext::set($validated['school_level']);

        return redirect()->back();
    }
}
