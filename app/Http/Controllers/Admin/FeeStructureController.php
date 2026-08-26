<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeeBreakdown;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Form;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();

        $formId = $request->input('form_id');
        $streamId = $request->input('stream_id');

        // Load streams for the selected form (for repopulating the dropdown)
        $streams = $formId ? Form::find($formId)?->streams()->orderBy('name')->get() ?? collect() : collect();

        $structures = FeeStructure::with(['form', 'stream', 'feeCategory', 'breakdowns'])
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->when($formId, fn ($q) => $q->where('form_id', $formId))
            ->when($formId && $streamId, fn ($q) => $q->where('stream_id', $streamId))
            ->orderBy('form_id')
            ->orderBy('stream_id')
            ->get()
            ->groupBy(function ($s) {
                $label = $s->form->name;
                if ($s->stream) {
                    $label .= ' - ' . $s->stream->name;
                } else {
                    $label .= ' - ' . __('All Streams');
                }
                return $label;
            });

        return view('admin.fees.structures.index', compact('structures', 'forms', 'streams', 'formId', 'streamId'));
    }

    public function create()
    {
        $forms = Form::active()->forCurrentLevel()->ordered()->get();

        return view('admin.fees.structures.create', compact('forms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'form_id' => ['required', 'exists:forms,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'fees' => ['required', 'array', 'min:1'],
            'fees.*.category_id' => ['required', 'exists:fee_categories,id'],
            'fees.*.amount' => ['required', 'numeric', 'min:0'],
            'fees.*.breakdowns' => ['nullable', 'array'],
            'fees.*.breakdowns.*.name' => ['required_with:fees.*.breakdowns', 'string', 'max:150'],
            'fees.*.breakdowns.*.amount' => ['required_with:fees.*.breakdowns', 'numeric', 'min:0'],
        ]);

        $currentSession = AcademicSession::current();
        if (!$currentSession) {
            return back()->with('error', __('No active academic session found.'))->withInput();
        }

        DB::transaction(function () use ($validated, $currentSession) {
            foreach ($validated['fees'] as $fee) {
                if ($fee['amount'] <= 0) continue;

                $structure = FeeStructure::updateOrCreate(
                    [
                        'academic_session_id' => $currentSession->id,
                        'form_id' => $validated['form_id'],
                        'stream_id' => $validated['stream_id'] ?? null,
                        'fee_category_id' => $fee['category_id'],
                    ],
                    ['amount' => $fee['amount']]
                );

                // Sync breakdowns
                $structure->breakdowns()->delete();
                if (!empty($fee['breakdowns'])) {
                    $order = 0;
                    foreach ($fee['breakdowns'] as $bd) {
                        if (empty($bd['name']) || $bd['amount'] <= 0) continue;
                        $structure->breakdowns()->create([
                            'name' => $bd['name'],
                            'amount' => $bd['amount'],
                            'sort_order' => $order++,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.fee-structures.index')
            ->with('success', __('Fee structure saved successfully.'));
    }

    public function edit(FeeStructure $feeStructure)
    {
        $feeStructure->load(['feeCategory', 'form', 'stream', 'breakdowns']);

        return view('admin.fees.structures.edit', compact('feeStructure'));
    }

    public function update(Request $request, FeeStructure $feeStructure)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'breakdowns' => ['nullable', 'array'],
            'breakdowns.*.name' => ['required_with:breakdowns', 'string', 'max:150'],
            'breakdowns.*.amount' => ['required_with:breakdowns', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $feeStructure) {
            $feeStructure->update(['amount' => $validated['amount']]);

            $feeStructure->breakdowns()->delete();
            if (!empty($validated['breakdowns'])) {
                $order = 0;
                foreach ($validated['breakdowns'] as $bd) {
                    if (empty($bd['name']) || $bd['amount'] <= 0) continue;
                    $feeStructure->breakdowns()->create([
                        'name' => $bd['name'],
                        'amount' => $bd['amount'],
                        'sort_order' => $order++,
                    ]);
                }
            }
        });

        return redirect()->route('admin.fee-structures.index')
            ->with('success', __('Fee amount updated.'));
    }

    public function destroy(FeeStructure $feeStructure)
    {
        $feeStructure->delete();

        return redirect()->route('admin.fee-structures.index')
            ->with('success', __('Fee structure entry deleted.'));
    }

    // ===== AJAX Endpoints =====

    public function getStreamsByForm(Form $form)
    {
        return response()->json(
            $form->streams()->where('is_active', true)->get(['streams.id', 'streams.name', 'streams.code'])
        );
    }

    public function loadCategories()
    {
        $categories = FeeCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_mandatory', 'is_tuition', 'is_boarding']);

        return response()->json($categories);
    }

    public function loadExisting(Request $request)
    {
        $request->validate([
            'form_id' => ['required', 'exists:forms,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        $currentSession = AcademicSession::current();

        $existing = FeeStructure::with('breakdowns')
            ->where('academic_session_id', $currentSession?->id)
            ->where('form_id', $request->form_id)
            ->where('stream_id', $request->stream_id)
            ->get()
            ->keyBy('fee_category_id');

        return response()->json($existing);
    }
}
