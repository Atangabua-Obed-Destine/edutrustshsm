<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read side of the audit trail.
 *
 * The table has been written to since the finance modules were built, but there
 * was no controller, route or view — it was write-only, so none of it could be
 * used in an investigation.
 */
class AuditLogController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'audit-log';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('audit-log.view', ['index', 'show']),
            static::can('audit-log.export', ['export']),
        ];
    }

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'actions' => $this->actionOptions(),
            'models' => $this->modelOptions(),
            'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user:id,first_name,last_name,email');

        return view('admin.audit-log.show', ['log' => $auditLog]);
    }

    /** Stream the filtered trail as CSV (streamed so a long history can't exhaust memory). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user:id,first_name,last_name');
        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'User', 'Action', 'Record', 'Record ID', 'IP', 'Changes']);

            $query->orderByDesc('created_at')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        optional($log->created_at)->format('Y-m-d H:i:s'),
                        $log->user?->full_name ?? '—',
                        $log->action,
                        class_basename($log->model_type ?? ''),
                        $log->model_id,
                        $log->ip_address,
                        json_encode($log->new_values ?? $log->old_values, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Shared filter set for the list and the export, so they never disagree. */
    private function filtered(Request $request)
    {
        return AuditLog::query()
            ->visible()
            ->when($request->input('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->input('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->input('model'), fn ($q, $v) => $q->where('model_type', 'like', '%'.$v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->input('search'), function ($q, $v) {
                $q->where(function ($inner) use ($v) {
                    $inner->where('ip_address', 'like', "%{$v}%")
                        ->orWhere('old_values', 'like', "%{$v}%")
                        ->orWhere('new_values', 'like', "%{$v}%");
                });
            });
    }

    /** @return array<int, string> */
    private function actionOptions(): array
    {
        return AuditLog::query()->visible()->distinct()->orderBy('action')->pluck('action')->all();
    }

    /** @return array<string, string> model_type => short label */
    private function modelOptions(): array
    {
        return AuditLog::query()->visible()->distinct()->whereNotNull('model_type')
            ->orderBy('model_type')->pluck('model_type')
            ->mapWithKeys(fn ($m) => [class_basename($m) => class_basename($m)])
            ->all();
    }
}
