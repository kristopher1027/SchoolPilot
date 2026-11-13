<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    private const MANAGERS = ['school_admin', 'principal', 'bursar'];

    public function index()
    {
        $components = DB::table('salary_components')->orderBy('type')->orderBy('name')->get();
        $assignments = DB::table('staff_salary_assignments as a')
            ->join('staff as s', 's.id', '=', 'a.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select('a.*', 's.staff_no', 'u.name as staff_name')
            ->orderBy('u.name')->get();
        $staff = Staff::with('user')->whereHas('user', fn ($q) => $q->where('is_active', true))->orderBy('staff_no')->get();
        $periods = DB::table('payroll_periods')->orderByDesc('year')->orderByDesc('month')->limit(24)->get();

        return view('tenant.payroll.index', compact('components', 'assignments', 'staff', 'periods'));
    }

    public function storeComponent(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:salary_components,name'],
            'type' => ['required', Rule::in(['allowance', 'deduction'])],
            'calculation' => ['required', Rule::in(['fixed', 'percentage'])],
            'default_value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        if ($data['calculation'] === 'percentage' && $data['default_value'] > 100) {
            return back()->withErrors(['default_value' => 'Percentage components cannot exceed 100%.'])->withInput();
        }
        $id = DB::table('salary_components')->insertGetId($data + [
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('payroll.component_created', 'salary_component', $id, null, $data, $request);

        return back()->with('success', 'Salary component created.');
    }

    public function storeAssignment(Request $request)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'base_salary' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'effective_from' => ['required', 'date'],
            'component_ids' => ['sometimes', 'array'],
            'component_ids.*' => ['required', 'integer', 'distinct', 'exists:salary_components,id'],
            'component_overrides' => ['sometimes', 'array'],
            'component_overrides.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        $start = Carbon::parse($data['effective_from'])->toDateString();
        if (strtolower($data['currency']) !== $data['currency']) {
            return back()->withErrors(['currency' => 'Currency must use three uppercase ISO currency letters.'])->withInput();
        }
        $future = DB::table('staff_salary_assignments')->where('staff_id', $data['staff_id'])
            ->where('is_active', true)->whereDate('effective_from', '>=', $start)->exists();
        if ($future) {
            return back()->withErrors(['effective_from' => 'An active salary assignment already starts on or after this date. End or deactivate it first.'])->withInput();
        }

        $actor = Auth::guard('school')->user();
        $id = DB::transaction(function () use ($data, $start, $actor, $request) {
            DB::table('staff_salary_assignments')->where('staff_id', $data['staff_id'])
                ->where('is_active', true)->whereDate('effective_from', '<', $start)
                ->where(function ($q) use ($start) {
                    $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start);
                })
                ->update(['effective_to' => Carbon::parse($start)->subDay()->toDateString(), 'updated_at' => now()]);

            $id = DB::table('staff_salary_assignments')->insertGetId([
                'staff_id' => $data['staff_id'],
                'base_salary' => $data['base_salary'],
                'currency' => $data['currency'],
                'effective_from' => $start,
                'effective_to' => null,
                'is_active' => true,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['component_ids'] ?? [] as $componentId) {
                $definition = DB::table('salary_components')->where('id', $componentId)->where('is_active', true)->first();
                abort_unless($definition, 422, 'A selected salary component is inactive.');
                DB::table('salary_assignment_components')->insert([
                    'salary_assignment_id' => $id,
                    'salary_component_id' => $componentId,
                    'amount_override' => $data['component_overrides'][$componentId] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit('payroll.salary_assigned', 'staff_salary_assignment', $id, null, [
                'staff_id' => $data['staff_id'], 'base_salary' => $data['base_salary'],
                'currency' => $data['currency'], 'effective_from' => $start,
            ], $request);

            return $id;
        });

        return back()->with('success', 'Staff salary assignment saved.');
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ]);
        if (strtoupper($data['currency']) !== $data['currency']) {
            return back()->withErrors(['currency' => 'Currency must use three uppercase ISO currency letters.'])->withInput();
        }
        $exists = DB::table('payroll_periods')->where('year', $data['year'])->where('month', $data['month'])->where('currency', $data['currency'])->exists();
        if ($exists) {
            return back()->withErrors(['month' => 'A payroll period already exists for this month and currency.'])->withInput();
        }
        $id = DB::table('payroll_periods')->insertGetId([
            'year' => $data['year'], 'month' => $data['month'], 'currency' => $data['currency'],
            'status' => 'draft', 'staff_count' => 0, 'gross_total' => 0, 'deductions_total' => 0, 'net_total' => 0,
            'created_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('payroll.period_created', 'payroll_period', $id, null, $data, $request);

        return redirect($this->schoolPath($request, '/payroll/'.$id))->with('success', 'Payroll period created. Generate the draft to calculate staff pay.');
    }

    public function show(Request $request, int $period)
    {
        $payroll = DB::table('payroll_periods')->where('id', $period)->first();
        abort_unless($payroll, 404);
        $items = DB::table('payroll_items')->where('payroll_period_id', $period)->orderBy('staff_name')->get();

        return view('tenant.payroll.show', compact('payroll', 'items'));
    }

    public function generate(Request $request, int $period)
    {
        $actor = Auth::guard('school')->user();
        DB::transaction(function () use ($period, $actor, $request) {
            $payroll = DB::table('payroll_periods')->where('id', $period)->lockForUpdate()->first();
            abort_unless($payroll, 404);
            abort_unless($payroll->status === 'draft', 422, 'Only draft payroll periods can be generated.');
            abort_if(DB::table('payroll_items')->where('payroll_period_id', $period)->exists(), 422, 'This payroll already has generated items.');

            $periodStart = Carbon::createFromDate((int) $payroll->year, (int) $payroll->month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $assignments = DB::table('staff_salary_assignments as a')
                ->join('staff as s', 's.id', '=', 'a.staff_id')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->where('a.is_active', true)
                ->where('a.currency', $payroll->currency)
                ->whereDate('a.effective_from', '<=', $periodEnd->toDateString())
                ->where(function ($q) use ($periodStart) {
                    $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $periodStart->toDateString());
                })
                ->where('u.is_active', true)
                ->select('a.*', 's.id as staff_id', 's.staff_no', 'u.name as staff_name')
                ->get();

            abort_if($assignments->isEmpty(), 422, 'No active salary assignments match this payroll month and currency.');
            $grossTotal = 0.0;
            $deductionsTotal = 0.0;
            $netTotal = 0.0;

            foreach ($assignments as $assignment) {
                $base = round((float) $assignment->base_salary, 2);
                $allowances = 0.0;
                $deductions = 0.0;
                $breakdown = ['base_salary' => $base, 'allowances' => [], 'deductions' => []];

                $components = DB::table('salary_assignment_components as ac')
                    ->join('salary_components as sc', 'sc.id', '=', 'ac.salary_component_id')
                    ->where('ac.salary_assignment_id', $assignment->id)
                    ->where('sc.is_active', true)
                    ->select('sc.name', 'sc.type', 'sc.calculation', 'sc.default_value', 'ac.amount_override')
                    ->get();

                foreach ($components as $component) {
                    $value = $component->amount_override !== null ? (float) $component->amount_override : (float) $component->default_value;
                    $amount = round($component->calculation === 'percentage' ? $base * $value / 100 : $value, 2);
                    if ($component->type === 'allowance') {
                        $allowances += $amount;
                        $breakdown['allowances'][] = ['name' => $component->name, 'amount' => $amount];
                    } else {
                        $deductions += $amount;
                        $breakdown['deductions'][] = ['name' => $component->name, 'amount' => $amount];
                    }
                }

                $gross = round($base + $allowances, 2);
                $deductions = round($deductions, 2);
                abort_if($deductions > $gross, 422, 'Deductions exceed gross pay for '.$assignment->staff_name.'. Review the salary components.');
                $net = round($gross - $deductions, 2);

                DB::table('payroll_items')->insert([
                    'payroll_period_id' => $period,
                    'staff_id' => $assignment->staff_id,
                    'salary_assignment_id' => $assignment->id,
                    'staff_name' => $assignment->staff_name,
                    'staff_no' => $assignment->staff_no,
                    'base_salary' => $base,
                    'allowances_total' => round($allowances, 2),
                    'deductions_total' => $deductions,
                    'gross_pay' => $gross,
                    'net_pay' => $net,
                    'breakdown' => json_encode($breakdown),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $grossTotal += $gross;
                $deductionsTotal += $deductions;
                $netTotal += $net;
            }

            DB::table('payroll_periods')->where('id', $period)->update([
                'staff_count' => $assignments->count(),
                'gross_total' => round($grossTotal, 2),
                'deductions_total' => round($deductionsTotal, 2),
                'net_total' => round($netTotal, 2),
                'updated_at' => now(),
            ]);
            $this->audit('payroll.draft_generated', 'payroll_period', $period, null, [
                'staff_count' => $assignments->count(), 'gross_total' => round($grossTotal, 2),
                'deductions_total' => round($deductionsTotal, 2), 'net_total' => round($netTotal, 2),
            ], $request);
        });

        return redirect($this->schoolPath($request, '/payroll/'.$period))->with('success', 'Payroll draft generated.');
    }

    public function approve(Request $request, int $period)
    {
        $actor = Auth::guard('school')->user();
        DB::transaction(function () use ($period, $actor, $request) {
            $payroll = DB::table('payroll_periods')->where('id', $period)->lockForUpdate()->first();
            abort_unless($payroll, 404);
            abort_unless($payroll->status === 'draft' && $payroll->staff_count > 0, 422, 'Generate a non-empty draft before approval.');
            DB::table('payroll_periods')->where('id', $period)->update([
                'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit('payroll.approved', 'payroll_period', $period, ['status' => 'draft'], ['status' => 'approved'], $request);
        });

        return back()->with('success', 'Payroll approved.');
    }

    public function markPaid(Request $request, int $period)
    {
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:150']]);
        $actor = Auth::guard('school')->user();
        DB::transaction(function () use ($period, $data, $actor, $request) {
            $payroll = DB::table('payroll_periods')->where('id', $period)->lockForUpdate()->first();
            abort_unless($payroll, 404);
            abort_unless($payroll->status === 'approved', 422, 'Only approved payroll can be marked as paid.');
            DB::table('payroll_periods')->where('id', $period)->update([
                'status' => 'paid', 'paid_by' => $actor->id, 'paid_at' => now(),
                'payment_reference' => $data['payment_reference'], 'updated_at' => now(),
            ]);
            $this->audit('payroll.marked_paid', 'payroll_period', $period, ['status' => 'approved'], [
                'status' => 'paid', 'payment_reference' => $data['payment_reference'],
            ], $request);
        });

        return back()->with('success', 'Payroll marked as paid. Record the actual bank transfer through your finance process; this action does not initiate a payment.');
    }

    public function payslips()
    {
        $staff = Staff::where('user_id', Auth::guard('school')->id())->firstOrFail();
        $items = DB::table('payroll_items as pi')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pi.payroll_period_id')
            ->where('pi.staff_id', $staff->id)
            ->whereIn('pp.status', ['approved', 'paid'])
            ->select('pi.*', 'pp.year', 'pp.month', 'pp.currency', 'pp.status as payroll_status', 'pp.paid_at')
            ->orderByDesc('pp.year')->orderByDesc('pp.month')->limit(36)->get();

        return view('tenant.payroll.payslips', compact('items'));
    }

    public function payslip(Request $request, int $item)
    {
        $staff = Staff::where('user_id', Auth::guard('school')->id())->firstOrFail();
        $payslip = DB::table('payroll_items as pi')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pi.payroll_period_id')
            ->where('pi.id', $item)->where('pi.staff_id', $staff->id)
            ->whereIn('pp.status', ['approved', 'paid'])
            ->select('pi.*', 'pp.year', 'pp.month', 'pp.currency', 'pp.status as payroll_status', 'pp.paid_at', 'pp.payment_reference')
            ->first();
        abort_unless($payslip, 404);

        return view('tenant.payroll.payslip', compact('payslip'));
    }

    private function audit(string $action, string $entity, int $id, mixed $old, mixed $new, Request $request): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::guard('school')->id(),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $id,
            'old_value' => $old === null ? null : json_encode($old),
            'new_value' => $new === null ? null : json_encode($new),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function schoolPath(Request $request, string $path): string
    {
        return '/school/'.$request->route('school').$path;
    }
}
