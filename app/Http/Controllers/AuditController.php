<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Read-only audit log viewer (audit_trail). Gated to supervisory roles in routes.
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['table', 'action', 'q']);

        $log = AuditTrail::query()
            ->when($filters['table'] ?? null, fn ($w, $v) => $w->where('table_name', $v))
            ->when($filters['action'] ?? null, fn ($w, $v) => $w->where('action_type', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('remarks', 'like', "%{$v}%")
                ->orWhere('modified_by', 'like', "%{$v}%")))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'log' => $log,
            'filters' => $filters,
            'tableList' => AuditTrail::query()->select('table_name')->distinct()->orderBy('table_name')->pluck('table_name'),
            'actionList' => ['INSERT', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT'],
        ]);
    }
}
