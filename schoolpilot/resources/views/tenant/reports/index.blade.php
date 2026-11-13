@extends('tenant.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
            <p class="mt-1 text-sm text-gray-600">School-wide operational, academic and financial summaries.</p>
        </div>
        <a href="{{ url('/school/'.request()->route('school').'/reports/export').'?'.http_build_query(request()->query()) }}" class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Export CSV</a>
    </div>

    <form method="GET" action="{{ url('/school/'.request()->route('school').'/reports') }}" class="grid gap-3 rounded-xl bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-5">
        <label class="text-sm font-medium text-gray-700">From date<input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm font-medium text-gray-700">To date<input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm font-medium text-gray-700">Academic session<select name="session_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected((string)$filters['session_id']===(string)$session->id)>{{ $session->name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium text-gray-700">Term<select name="term_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All terms</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected((string)$filters['term_id']===(string)$term->id)>{{ $term->name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium text-gray-700">Class<select name="class_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((string)$filters['class_id']===(string)$class->id)>{{ $class->name }}</option>@endforeach</select></label>
        <div class="flex items-center gap-2 lg:col-span-5"><button class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ url('/school/'.request()->route('school').'/reports') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Clear</a><span class="text-xs text-gray-500">Date filters apply to dated records; session, term and class filters apply where relevant.</span></div>
    </form>

    @if($role !== 'bursar')
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900">Enrollment & admissions</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([['Total students',$data['enrollment']['total']],['Active students',$data['enrollment']['active']]] as [$label,$value])
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($value) }}</p></div>
            @endforeach
            @if($role === 'dean' || in_array($role, ['school_admin','principal','vice_principal'], true))
                @foreach([['Admissions received',$data['admissions']['total']],['Accepted applications',$data['admissions']['accepted']]] as [$label,$value])<div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($value) }}</p></div>@endforeach
            @endif
        </div>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <div class="rounded-xl bg-white p-4 shadow"><h3 class="font-semibold">Student status</h3><div class="mt-3 grid grid-cols-2 gap-3 text-sm">@foreach(['active','graduated','transferred','withdrawn'] as $status)<div class="flex justify-between border-b py-2"><span class="capitalize text-gray-600">{{ $status }}</span><strong>{{ number_format($data['enrollment'][$status]) }}</strong></div>@endforeach</div></div>
            @if($role === 'dean' || in_array($role, ['school_admin','principal','vice_principal'], true))
            <div class="rounded-xl bg-white p-4 shadow"><h3 class="font-semibold">Admission pipeline</h3><div class="mt-3 grid grid-cols-2 gap-3 text-sm">@foreach(['submitted','under_review','accepted','waitlisted','rejected','converted'] as $status)<div class="flex justify-between border-b py-2"><span class="capitalize text-gray-600">{{ str_replace('_',' ',$status) }}</span><strong>{{ number_format($data['admissions'][$status]) }}</strong></div>@endforeach</div></div>
            @endif
        </div>
        <div class="mt-3 rounded-xl bg-white p-4 shadow"><h3 class="font-semibold">Student strength by class</h3><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-gray-500"><tr><th class="py-2">Class</th><th class="py-2 text-right">Students</th></tr></thead><tbody>@forelse($data['enrollment']['by_class'] as $row)<tr class="border-t"><td class="py-2">{{ $row->class_name }}</td><td class="py-2 text-right font-semibold">{{ number_format($row->student_count) }}</td></tr>@empty<tr><td colspan="2" class="py-3 text-gray-500">No enrollment records.</td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900">Attendance & academic performance</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([['Attendance rate',$data['attendance']['rate'].'%'],['Present',$data['attendance']['present']],['Absent',$data['attendance']['absent']],['Late',$data['attendance']['late']],['Excused',$data['attendance']['excused']]] as [$label,$value])
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</p></div>
            @endforeach
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">Score entries</p><p class="mt-2 text-2xl font-bold">{{ number_format($data['academics']['score_entries']) }}</p></div>
            <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">Average entered score</p><p class="mt-2 text-2xl font-bold">{{ number_format($data['academics']['average_score'],2) }}</p></div>
        </div>
        <div class="mt-3 rounded-xl bg-white p-4 shadow"><h3 class="font-semibold">Academic performance by class</h3><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-gray-500"><tr><th class="py-2">Class</th><th class="py-2 text-right">Score entries</th><th class="py-2 text-right">Average score</th></tr></thead><tbody>@forelse($data['academics']['by_class'] as $row)<tr class="border-t"><td class="py-2">{{ $row->class_name }}</td><td class="py-2 text-right">{{ number_format($row->score_entries) }}</td><td class="py-2 text-right font-semibold">{{ number_format($row->average_score,2) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-gray-500">No score entries for these filters.</td></tr>@endforelse</tbody></table></div></div>
    </section>

    @endif

    @if(in_array($role, ['school_admin','principal','vice_principal','bursar'], true))
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900">Finance</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([['Invoiced','₦'.number_format($data['finance']['invoiced'],2)],['Collected','₦'.number_format($data['finance']['collected'],2)],['Outstanding','₦'.number_format($data['finance']['outstanding'],2)],['Payments recorded',number_format($data['finance']['payments'])]] as [$label,$value])
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-xl font-bold text-gray-900">{{ $value }}</p></div>
            @endforeach
        </div>
    </section>

    @endif

    @if(in_array($role, ['school_admin','principal','vice_principal'], true))
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900">Staff & operations</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([['Staff profiles',$data['staff']['total']],['Active staff accounts',$data['staff']['active_users']],['Library books',$data['library']['books']],['Library copies',$data['library']['copies']],['Active library loans',$data['library']['active_loans']],['Overdue library loans',$data['library']['overdue_loans']],['Pending reservations',$data['library']['reservations']],['Active inventory items',$data['inventory']['items']],['Low-stock items',$data['inventory']['low_stock_items']],['Assets in service',$data['inventory']['assets']],['Asset maintenance records',$data['inventory']['maintenance_records']],['Pending purchase requests',$data['inventory']['pending_purchase_requests']],['Active vehicles',$data['transport']['vehicles']],['Active transport routes',$data['transport']['active_routes']],['Students assigned to transport',$data['transport']['active_assignments']],['Transport incidents',$data['transport']['incidents']]] as [$label,$value])
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($value) }}</p></div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-gray-500">Operational figures reflect current records. Date, session, term and class filters do not apply to every operational count.</p>
    </section>
    @endif
</div>
@endsection
