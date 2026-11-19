@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div><h1 class="text-2xl font-bold text-gray-900">Payroll Management</h1><p class="mt-1 text-sm text-gray-600">Configure salary components, assign staff salaries and process monthly payroll.</p></div>

    <section class="rounded-xl bg-white p-5 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Payroll financial summary</h2>
                <p class="mt-1 text-sm text-gray-500">Annual totals are grouped by currency; amounts in different currencies are never combined.</p>
            </div>
            <form method="GET" action="{{ url('/school/'.request()->route('school').'/payroll') }}" class="flex items-end gap-2">
                <label class="text-sm font-medium text-gray-700">Financial year
                    <select name="year" class="mt-1 block rounded-lg border-gray-300 text-sm">
                        @foreach($availableYears as $year)<option value="{{ $year }}" @selected($selectedYear===(int)$year)>{{ $year }}</option>@endforeach
                        @if(!$availableYears->contains($selectedYear))<option value="{{ $selectedYear }}" selected>{{ $selectedYear }}</option>@endif
                    </select>
                </label>
                <button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">View</button>
            </form>
        </div>
        @if($financialSummaries->isEmpty())
            <p class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">No payroll periods have been created for {{ $selectedYear }}.</p>
        @else
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach($financialSummaries as $summary)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-900">{{ $summary->currency }} <span class="text-sm font-normal text-gray-500">{{ $summary->period_count }} periods</span></h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-gray-500">Gross payroll</dt><dd class="mt-1 font-semibold">{{ number_format($summary->gross_total, 2) }}</dd></div>
                            <div><dt class="text-gray-500">Deductions</dt><dd class="mt-1 font-semibold">{{ number_format($summary->deductions_total, 2) }}</dd></div>
                            <div><dt class="text-gray-500">Net payroll</dt><dd class="mt-1 font-semibold">{{ number_format($summary->net_total, 2) }}</dd></div>
                            <div><dt class="text-gray-500">Staff-periods</dt><dd class="mt-1 font-semibold">{{ number_format($summary->staff_period_count) }}</dd></div>
                        </dl>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-700">Draft: {{ $summary->draft_count }}</span>
                            <span class="rounded-full bg-blue-50 px-2 py-1 text-blue-800">Approved: {{ $summary->approved_count }}</span>
                            <span class="rounded-full bg-green-50 px-2 py-1 text-green-800">Paid: {{ $summary->paid_count }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-xl bg-white p-5 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Salary components</h2>
        <p class="mt-1 text-sm text-gray-500">Create recurring allowances and deductions. Percentage values are calculated against each staff member's base salary.</p>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/components') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <label class="text-sm font-medium text-gray-700">Component name<input required name="name" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" placeholder="Housing allowance"></label>
            <label class="text-sm font-medium text-gray-700">Type<select name="type" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></label>
            <label class="text-sm font-medium text-gray-700">Calculation<select name="calculation" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="fixed">Fixed amount</option><option value="percentage">Percentage of base</option></select></label>
            <label class="text-sm font-medium text-gray-700">Default value<input required type="number" min="0" step="0.01" name="default_value" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" value="0"></label>
            <div class="flex items-end"><button class="w-full rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Add component</button></div>
        </form>
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Calculation</th><th class="px-3 py-2">Default</th></tr></thead><tbody class="divide-y">
            @forelse($components as $component)<tr><td class="px-3 py-2 font-medium">{{ $component->name }}</td><td class="px-3 py-2">{{ ucfirst($component->type) }}</td><td class="px-3 py-2">{{ ucfirst($component->calculation) }}</td><td class="px-3 py-2">{{ number_format($component->default_value, 2) }}{{ $component->calculation==='percentage'?'%':'' }}</td></tr>
            @empty<tr><td colspan="4" class="px-3 py-5 text-center text-gray-500">No salary components configured.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="rounded-xl bg-white p-5 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Assign staff salary</h2>
        <p class="mt-1 text-sm text-gray-500">A new assignment closes the previous open-ended assignment from the day before its effective date. Generated payroll keeps a snapshot of the amounts.</p>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/assignments') }}" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-medium text-gray-700">Staff member<select required name="staff_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="">Choose staff</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->user->name }} — {{ $member->staff_no }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-gray-700">Base salary<input required type="number" min="0.01" step="0.01" name="base_salary" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" placeholder="250000"></label>
                <label class="text-sm font-medium text-gray-700">Currency<input required name="currency" value="NGN" minlength="3" maxlength="3" pattern="[A-Z]{3}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm font-medium text-gray-700">Effective from<input required type="date" name="effective_from" value="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            </div>
            @if($components->isNotEmpty())
                <div><h3 class="mb-2 text-sm font-semibold text-gray-800">Attach salary components (optional)</h3><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($components as $i=>$component)
                        @if($component->is_active)
                            <label class="rounded-lg border border-gray-200 p-3 text-sm"><span class="flex items-center gap-2 font-medium"><input type="checkbox" name="component_ids[]" value="{{ $component->id }}"> {{ $component->name }} ({{ ucfirst($component->type) }})</span><span class="mt-2 block text-xs text-gray-500">Override default {{ number_format($component->default_value,2) }}{{ $component->calculation==='percentage'?'%':'' }}<input type="number" min="0" step="0.01" name="component_overrides[{{ $component->id }}]" placeholder="Leave blank to use default" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></span></label>
                        @endif
                    @endforeach
                </div></div>
            @endif
            <button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Save salary assignment</button>
        </form>
        <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Staff</th><th class="px-3 py-2">Base salary</th><th class="px-3 py-2">Currency</th><th class="px-3 py-2">Effective</th><th class="px-3 py-2">Status</th></tr></thead><tbody class="divide-y">
            @forelse($assignments as $assignment)<tr><td class="px-3 py-2"><p class="font-medium">{{ $assignment->staff_name }}</p><p class="text-xs text-gray-500">{{ $assignment->staff_no }}</p></td><td class="px-3 py-2">{{ number_format($assignment->base_salary,2) }}</td><td class="px-3 py-2">{{ $assignment->currency }}</td><td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($assignment->effective_from)->format('d M Y') }} @if($assignment->effective_to) – {{ \Illuminate\Support\Carbon::parse($assignment->effective_to)->format('d M Y') }}@else onward @endif</td><td class="px-3 py-2">{{ $assignment->is_active?'Active':'Inactive' }}</td></tr>
            @empty<tr><td colspan="5" class="px-3 py-5 text-center text-gray-500">No salary assignments yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="rounded-xl bg-white p-5 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Create payroll period</h2>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/payroll/periods') }}" class="mt-4 grid gap-3 sm:grid-cols-4">
            @csrf
            <label class="text-sm font-medium text-gray-700">Year<input required type="number" min="2020" max="2100" name="year" value="{{ now()->year }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm font-medium text-gray-700">Month<select required name="month" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected($month===now()->month)>{{ \Illuminate\Support\Carbon::create()->month($month)->format('F') }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-gray-700">Currency<input required name="currency" value="NGN" minlength="3" maxlength="3" pattern="[A-Z]{3}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            <div class="flex items-end"><button class="w-full rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Create period</button></div>
        </form>
        <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Period</th><th class="px-3 py-2">Currency</th><th class="px-3 py-2">Staff</th><th class="px-3 py-2">Net total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr></thead><tbody class="divide-y">
            @forelse($periods as $period)<tr><td class="px-3 py-2 font-medium">{{ \Illuminate\Support\Carbon::createFromDate($period->year,$period->month,1)->format('F Y') }}</td><td class="px-3 py-2">{{ $period->currency }}</td><td class="px-3 py-2">{{ $period->staff_count }}</td><td class="px-3 py-2">{{ number_format($period->net_total,2) }}</td><td class="px-3 py-2">{{ ucfirst($period->status) }}</td><td class="px-3 py-2"><a class="font-semibold text-blue-800 hover:underline" href="{{ url('/school/'.request()->route('school').'/payroll/'.$period->id) }}">Open</a></td></tr>
            @empty<tr><td colspan="6" class="px-3 py-5 text-center text-gray-500">No payroll periods yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
