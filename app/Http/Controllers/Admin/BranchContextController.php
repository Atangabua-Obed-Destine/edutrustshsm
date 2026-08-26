<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    /**
     * Switch the active branch (or 'all' for the consolidated owner view).
     */
    public function switch(Request $request)
    {
        $request->validate([
            'branch' => ['required', 'string'],
        ]);

        BranchContext::set($request->input('branch'));

        return redirect()->back();
    }
}
