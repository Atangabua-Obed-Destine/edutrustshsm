<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\IssuedDocument;
use App\Support\Listing;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The register of certificates and records handed out: every copy printed,
 * with its serial, who printed it and when. Reading it is reading the pupils'
 * exit records, so it shares their permission.
 */
class IssuedDocumentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student-exit';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [static::can('student-exit.view', ['index'])];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $type = $request->input('type');
        $knownType = array_key_exists((string) $type, IssuedDocument::PREFIX);

        $query = IssuedDocument::query()
            ->select('issued_documents.*')
            ->with(['student', 'issuedBy'])
            ->when($knownType, fn ($q) => $q->where('issued_documents.type', $type))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('issued_documents.serial', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->where('student_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            }));

        $list = (new Listing($request, 'issued-documents', __('Certificates issued'), [
            'serial' => ['label' => __('Serial No.'), 'sort' => 'issued_documents.id', 'value' => fn ($d) => $d->serial],
            'type' => ['label' => __('Document'), 'sort' => 'issued_documents.type', 'value' => fn ($d) => IssuedDocument::typeLabel($d->type)],
            'pupil' => ['label' => __('Pupil'), 'value' => fn ($d) => trim(($d->student?->first_name ?? '').' '.($d->student?->last_name ?? ''))],
            'matricule' => ['label' => __('Matricule'), 'value' => fn ($d) => $d->student?->student_id],
            'issued' => ['label' => __('Issued on'), 'sort' => 'issued_documents.issued_at', 'value' => fn ($d) => $d->issued_at],
            'by' => ['label' => __('Issued by'), 'value' => fn ($d) => $d->issuedBy?->full_name],
        ], default: 'serial', defaultDir: 'desc'))->filters([
            __('Search') => $search ?: null,
            __('Document') => $knownType ? IssuedDocument::typeLabel($type) : null,
        ]);

        if ($download = $list->export($query)) {
            return $download;
        }

        return view('admin.issued-documents.index', [
            'documents' => $list->paginate($query),
            'list' => $list,
            'search' => $search,
            'type' => $type,
        ]);
    }
}
