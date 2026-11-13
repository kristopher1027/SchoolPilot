<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $data = $this->visibleReportData($this->buildReportData($filters));
        $sessions = DB::table('academic_sessions')->orderByDesc('start_date')->get();
        $terms = DB::table('terms')->when($filters['session_id'], fn ($q) => $q->where('academic_session_id', $filters['session_id']))->orderBy('start_date')->get();
        $classes = DB::table('classes')->orderBy('name')->get();
        $role = Auth::guard('school')->user()->role;

        return view('tenant.reports.index', compact('filters', 'data', 'sessions', 'terms', 'classes', 'role'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $data = $this->visibleReportData($this->buildReportData($filters));
        $rows = $this->exportRows($data);
        $filename = 'schoolpilot-report-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Report area', 'Metric', 'Value']);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        if (!empty($data['from']) && !empty($data['to']) && strtotime($data['to']) < strtotime($data['from'])) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        if (!empty($data['term_id'])) {
            $term = DB::table('terms')->where('id', $data['term_id'])->first();
            abort_unless($term, 404);
            if (!empty($data['session_id']) && (int) $term->academic_session_id !== (int) $data['session_id']) {
                abort(422, 'The selected term does not belong to the selected academic session.');
            }
            $data['session_id'] = $data['session_id'] ?? $term->academic_session_id;
        }

        return array_merge(['from' => null, 'to' => null, 'session_id' => null, 'term_id' => null, 'class_id' => null], $data);
    }

    private function buildReportData(array $filters): array
    {
        $data = [
            'enrollment' => ['total' => 0, 'active' => 0, 'graduated' => 0, 'transferred' => 0, 'withdrawn' => 0, 'by_class' => collect()],
            'admissions' => ['total' => 0, 'submitted' => 0, 'under_review' => 0, 'accepted' => 0, 'waitlisted' => 0, 'rejected' => 0, 'converted' => 0],
            'attendance' => ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'rate' => 0],
            'academics' => ['score_entries' => 0, 'average_score' => 0, 'by_class' => collect()],
            'finance' => ['invoiced' => 0, 'collected' => 0, 'outstanding' => 0, 'payments' => 0],
            'staff' => ['total' => 0, 'active_users' => 0],
            'library' => ['books' => 0, 'copies' => 0, 'active_loans' => 0, 'overdue_loans' => 0, 'reservations' => 0],
            'inventory' => ['items' => 0, 'low_stock_items' => 0, 'total_stock_quantity' => 0, 'assets' => 0, 'maintenance_records' => 0, 'pending_purchase_requests' => 0],
            'transport' => ['vehicles' => 0, 'active_routes' => 0, 'active_assignments' => 0, 'incidents' => 0],
        ];

        if (Schema::hasTable('students')) {
            $students = DB::table('students')->when($filters['class_id'], fn ($q) => $q->where('class_id', $filters['class_id']));
            $data['enrollment']['total'] = (clone $students)->count();
            foreach (['active', 'graduated', 'transferred', 'withdrawn'] as $status) {
                $data['enrollment'][$status] = (clone $students)->where('status', $status)->count();
            }
            $data['enrollment']['by_class'] = DB::table('students as s')
                ->leftJoin('classes as c', 'c.id', '=', 's.class_id')
                ->selectRaw("COALESCE(c.name, 'Unassigned') as class_name, COUNT(*) as student_count")
                ->when($filters['class_id'], fn ($q) => $q->where('s.class_id', $filters['class_id']))
                ->groupBy('c.id', 'c.name')->orderBy('class_name')->get();
        }

        if (Schema::hasTable('admission_applications')) {
            $applications = DB::table('admission_applications')->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));
            $data['admissions']['total'] = (clone $applications)->count();
            foreach (['submitted', 'under_review', 'accepted', 'waitlisted', 'rejected', 'converted'] as $status) {
                $data['admissions'][$status] = (clone $applications)->where('status', $status)->count();
            }
        }

        if (Schema::hasTable('attendance_records')) {
            $attendance = DB::table('attendance_records')
                ->when($filters['class_id'], fn ($q) => $q->where('class_id', $filters['class_id']))
                ->when($filters['from'], fn ($q) => $q->whereDate('date', '>=', $filters['from']))
                ->when($filters['to'], fn ($q) => $q->whereDate('date', '<=', $filters['to']));
            $data['attendance']['total'] = (clone $attendance)->count();
            foreach (['present', 'absent', 'late', 'excused'] as $status) {
                $data['attendance'][$status] = (clone $attendance)->where('status', $status)->count();
            }
            $attended = $data['attendance']['present'] + $data['attendance']['late'];
            $eligible = $data['attendance']['total'] - $data['attendance']['excused'];
            $data['attendance']['rate'] = $eligible > 0 ? round(($attended / $eligible) * 100, 1) : 0;
        }

        if (Schema::hasTable('score_entries')) {
            $scores = DB::table('score_entries')
                ->when($filters['class_id'], fn ($q) => $q->where('class_id', $filters['class_id']))
                ->when($filters['session_id'], fn ($q) => $q->where('academic_session_id', $filters['session_id']))
                ->when($filters['term_id'], fn ($q) => $q->where('term_id', $filters['term_id']));
            $data['academics']['score_entries'] = (clone $scores)->count();
            $data['academics']['average_score'] = round((float) ((clone $scores)->avg('score') ?? 0), 2);
            $data['academics']['by_class'] = DB::table('score_entries as se')
                ->join('classes as c', 'c.id', '=', 'se.class_id')
                ->select('c.name as class_name')
                ->selectRaw('COUNT(se.id) as score_entries, ROUND(AVG(se.score), 2) as average_score')
                ->when($filters['class_id'], fn ($q) => $q->where('se.class_id', $filters['class_id']))
                ->when($filters['session_id'], fn ($q) => $q->where('se.academic_session_id', $filters['session_id']))
                ->when($filters['term_id'], fn ($q) => $q->where('se.term_id', $filters['term_id']))
                ->groupBy('c.id', 'c.name')->orderBy('c.name')->get();
        }

        if (Schema::hasTable('invoices')) {
            $invoices = DB::table('invoices')
                ->when($filters['class_id'], function ($q) use ($filters) {
                    $q->whereIn('student_id', DB::table('students')->select('id')->where('class_id', $filters['class_id']));
                })
                ->when($filters['session_id'], fn ($q) => $q->where('academic_session_id', $filters['session_id']))
                ->when($filters['term_id'], fn ($q) => $q->where('term_id', $filters['term_id']))
                ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
                ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));
            $data['finance']['invoiced'] = round((float) (clone $invoices)->sum('total_amount'), 2);
            $data['finance']['outstanding'] = round((float) (clone $invoices)->sum('balance'), 2);
            if (Schema::hasTable('payments')) {
                $payments = DB::table('payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')
                    ->when($filters['class_id'], function ($q) use ($filters) {
                        $q->whereIn('i.student_id', DB::table('students')->select('id')->where('class_id', $filters['class_id']));
                    })
                    ->when($filters['session_id'], fn ($q) => $q->where('i.academic_session_id', $filters['session_id']))
                    ->when($filters['term_id'], fn ($q) => $q->where('i.term_id', $filters['term_id']))
                    ->when($filters['from'], fn ($q) => $q->whereDate('p.paid_at', '>=', $filters['from']))
                    ->when($filters['to'], fn ($q) => $q->whereDate('p.paid_at', '<=', $filters['to']));
                $data['finance']['collected'] = round((float) (clone $payments)->sum('p.amount'), 2);
                $data['finance']['payments'] = (clone $payments)->count();
            } else {
                $data['finance']['collected'] = round((float) (clone $invoices)->sum('amount_paid'), 2);
                $data['finance']['payments'] = 0;
            }
        }

        if (Schema::hasTable('staff')) {
            $data['staff']['total'] = DB::table('staff')->count();
            $data['staff']['active_users'] = DB::table('staff as s')->join('users as u', 'u.id', '=', 's.user_id')->where('u.is_active', true)->count();
        }

        if (Schema::hasTable('library_books')) $data['library']['books'] = DB::table('library_books')->count();
        if (Schema::hasTable('library_copies')) $data['library']['copies'] = DB::table('library_copies')->count();
        if (Schema::hasTable('library_loans')) {
            $loans = DB::table('library_loans')->whereNull('returned_at');
            $data['library']['active_loans'] = (clone $loans)->count();
            $data['library']['overdue_loans'] = (clone $loans)->whereDate('due_at', '<', now()->toDateString())->count();
        }
        if (Schema::hasTable('library_reservations')) $data['library']['reservations'] = DB::table('library_reservations')->where('status', 'pending')->count();

        if (Schema::hasTable('inventory_items') && Schema::hasTable('inventory_stocks')) {
            $data['inventory']['items'] = DB::table('inventory_items')->where('is_active', true)->count();
            $stock = DB::table('inventory_items as i')->leftJoin('inventory_stocks as s', 's.item_id', '=', 'i.id')
                ->select('i.id', 'i.reorder_level')->selectRaw('COALESCE(SUM(s.quantity), 0) as quantity')
                ->where('i.is_active', true)->groupBy('i.id', 'i.reorder_level')->get();
            $data['inventory']['total_stock_quantity'] = round((float) $stock->sum('quantity'), 2);
            $data['inventory']['low_stock_items'] = $stock->filter(fn ($row) => (float) $row->quantity <= (float) $row->reorder_level)->count();
        }
        if (Schema::hasTable('assets')) $data['inventory']['assets'] = DB::table('assets')->whereNotIn('status', ['disposed', 'retired'])->count();
        if (Schema::hasTable('asset_maintenance')) $data['inventory']['maintenance_records'] = DB::table('asset_maintenance')->count();
        if (Schema::hasTable('purchase_requests')) $data['inventory']['pending_purchase_requests'] = DB::table('purchase_requests')->where('status', 'pending')->count();

        if (Schema::hasTable('transport_vehicles')) $data['transport']['vehicles'] = DB::table('transport_vehicles')->where('status', 'active')->count();
        if (Schema::hasTable('transport_routes')) $data['transport']['active_routes'] = DB::table('transport_routes')->where('status', 'active')->count();
        if (Schema::hasTable('transport_student_assignments')) $data['transport']['active_assignments'] = DB::table('transport_student_assignments')->where('status', 'active')->count();
        if (Schema::hasTable('transport_incidents')) $data['transport']['incidents'] = DB::table('transport_incidents')->count();

        return $data;
    }

    private function visibleReportData(array $data): array
    {
        $role = Auth::guard('school')->user()->role;

        if ($role === 'bursar') {
            return ['finance' => $data['finance']];
        }

        if (in_array($role, ['dean', 'hod'], true)) {
            return [
                'enrollment' => $data['enrollment'],
                'attendance' => $data['attendance'],
                'academics' => $data['academics'],
            ];
        }

        return $data;
    }

    private function exportRows(array $data): array
    {
        $rows = [];
        foreach ($data as $area => $metrics) {
            foreach ($metrics as $metric => $value) {
                if ($value instanceof \Illuminate\Support\Collection) {
                    foreach ($value as $item) {
                        foreach ((array) $item as $key => $itemValue) {
                            $rows[] = [$area, $metric . ' - ' . $key, $itemValue];
                        }
                    }
                } else {
                    $rows[] = [$area, $metric, $value];
                }
            }
        }
        return $rows;
    }
}
