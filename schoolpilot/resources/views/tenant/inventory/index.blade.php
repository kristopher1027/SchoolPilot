@extends('tenant.layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div><h3 class="text-2xl font-bold text-gray-800">Inventory, Assets & Procurement</h3><p class="text-sm text-gray-500">Track school supplies, stock movements, purchasing, assets and maintenance.</p></div>
</div>
@if(session('success'))<div class="mb-5 rounded border border-green-200 bg-green-50 p-4 text-green-800" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded border border-red-200 bg-red-50 p-4 text-red-800"><p class="font-semibold">Please correct the following:</p><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(['items'=>'Inventory items','low_stock'=>'Low-stock items','assets'=>'Active assets','pending_requests'=>'Pending purchase requests'] as $key=>$label)
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ $summary[$key] }}</p></div>
    @endforeach
</div>
<div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Create inventory item</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/items') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
            <input name="sku" required maxlength="80" placeholder="SKU / item code" class="rounded border-gray-300">
            <input name="name" required maxlength="180" placeholder="Item name" class="rounded border-gray-300">
            <select name="category_id" class="rounded border-gray-300"><option value="">Category (optional)</option>@foreach($categories->where('category_type','stock') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
            <input name="unit" required value="piece" placeholder="Unit (piece, pack, litre...)" class="rounded border-gray-300">
            <input type="number" name="reorder_level" min="0" step="0.01" required value="0" placeholder="Low-stock threshold" class="rounded border-gray-300">
            <input name="description" placeholder="Description" class="rounded border-gray-300">
            <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white sm:col-span-2">Save item</button>
        </form>
    </section>
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Record stock movement</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/movements') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
            <select name="item_id" required class="rounded border-gray-300"><option value="">Select item</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>@endforeach</select>
            <select name="location_id" required class="rounded border-gray-300"><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
            <select name="movement_type" required class="rounded border-gray-300"><option value="receipt">Stock received</option><option value="issue">Stock issued</option><option value="adjustment">Set stock count</option></select>
            <input type="number" name="quantity" required min="0.01" step="0.01" placeholder="Quantity" class="rounded border-gray-300">
            <input name="reference" placeholder="Reference (optional)" class="rounded border-gray-300">
            <input name="notes" placeholder="Notes (optional)" class="rounded border-gray-300">
            <button class="rounded bg-indigo-700 px-4 py-2 font-semibold text-white sm:col-span-2">Record movement</button>
        </form>
    </section>
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Add supplier</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/suppliers') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
            <input name="name" required placeholder="Supplier / company name" class="rounded border-gray-300">
            <input name="contact_person" placeholder="Contact person" class="rounded border-gray-300">
            <input type="email" name="email" placeholder="Email" class="rounded border-gray-300">
            <input name="phone" placeholder="Phone" class="rounded border-gray-300">
            <input name="address" placeholder="Address" class="rounded border-gray-300">
            <input name="notes" placeholder="Notes" class="rounded border-gray-300">
            <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white sm:col-span-2">Save supplier</button>
        </form>
        <p class="mt-3 text-xs text-gray-500">{{ $suppliers->count() }} active suppliers</p>
    </section>
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Setup categories and stock locations</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/categories') }}" class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">@csrf
            <input name="name" required placeholder="Category name" class="rounded border-gray-300">
            <select name="category_type" class="rounded border-gray-300"><option value="stock">Stock</option><option value="asset">Asset</option></select>
            <button class="rounded bg-gray-800 px-3 py-2 text-white">Add category</button>
        </form>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/locations') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-3">@csrf
            <input name="name" required placeholder="Store / location name" class="rounded border-gray-300">
            <input name="description" placeholder="Description" class="rounded border-gray-300">
            <button class="rounded bg-gray-800 px-3 py-2 text-white">Add location</button>
        </form>
        <p class="mt-3 text-xs text-gray-500">{{ $categories->count() }} categories · {{ $locations->count() }} locations</p>
    </section>
</div>
<section class="mb-6 overflow-hidden rounded-lg bg-white shadow">
    <div class="border-b p-5"><h4 class="font-bold">Stock register and low-stock alerts</h4><p class="text-sm text-gray-500">Quantities are shown by storage location. Low stock compares total on-hand quantity with the item's reorder level.</p></div>
    <div class="overflow-x-auto"><table class="min-w-full divide-y text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">SKU / Item</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Location balances</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Reorder level</th><th class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y">
    @forelse($items as $item)@php($total = $item->stocks->sum('quantity'))
    <tr><td class="px-4 py-3 font-medium">{{ $item->sku }}<div class="text-xs text-gray-500">{{ $item->name }}</div></td><td class="px-4 py-3">{{ $item->category?->name ?? '—' }}</td><td class="px-4 py-3">@forelse($item->stocks as $stock)<div>{{ $stock->location?->name }}: {{ number_format((float)$stock->quantity,2) }} {{ $item->unit }}</div>@empty<span class="text-gray-400">No stock recorded</span>@endforelse</td><td class="px-4 py-3 font-semibold">{{ number_format((float)$total,2) }} {{ $item->unit }}</td><td class="px-4 py-3">{{ number_format((float)$item->reorder_level,2) }}</td><td class="px-4 py-3">@if($total <= (float)$item->reorder_level)<span class="font-semibold text-red-700">Low stock</span>@else<span class="text-green-700">In stock</span>@endif</td></tr>
    @empty<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No inventory items yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
<div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Submit purchase request</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/purchase-requests') }}" class="space-y-3">@csrf
            <textarea name="reason" rows="2" placeholder="Reason / business need" class="w-full rounded border-gray-300"></textarea>
            @for($i=0;$i<3;$i++)<div class="grid grid-cols-3 gap-2"><select name="items[{{ $i }}][item_id]" class="col-span-2 rounded border-gray-300"><option value="">Item {{ $i+1 }} (optional)</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select><input type="number" name="items[{{ $i }}][quantity]" min="0.01" step="0.01" placeholder="Qty" class="rounded border-gray-300"></div>@endfor
            <button class="rounded bg-indigo-700 px-4 py-2 font-semibold text-white">Submit request</button>
        </form>
    </section>
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Create purchase order</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/purchase-orders') }}" class="space-y-3">@csrf
            <select name="supplier_id" required class="w-full rounded border-gray-300"><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            <select name="purchase_request_id" class="w-full rounded border-gray-300"><option value="">Link approved request (optional)</option>@foreach($requests->where('status','approved') as $pr)<option value="{{ $pr->id }}">{{ $pr->request_no }}</option>@endforeach</select>
            <input type="date" name="expected_at" min="{{ today()->toDateString() }}" class="w-full rounded border-gray-300">
            @for($i=0;$i<3;$i++)<div class="grid grid-cols-5 gap-2"><select name="items[{{ $i }}][item_id]" class="col-span-2 rounded border-gray-300"><option value="">Item {{ $i+1 }}</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select><input type="number" name="items[{{ $i }}][quantity_ordered]" min="0.01" step="0.01" placeholder="Qty" class="rounded border-gray-300"><input type="number" name="items[{{ $i }}][unit_cost]" min="0" step="0.01" placeholder="Unit ₦" class="col-span-2 rounded border-gray-300"></div>@endfor
            <button class="rounded bg-blue-700 px-4 py-2 font-semibold text-white">Create order</button>
        </form>
    </section>
</div>
<section class="mb-6 overflow-hidden rounded-lg bg-white shadow"><div class="border-b p-5"><h4 class="font-bold">Purchase requests</h4></div><div class="overflow-x-auto"><table class="min-w-full divide-y text-sm"><thead class="bg-gray-50 text-left"><tr><th class="px-4 py-3">Request</th><th class="px-4 py-3">Requested items</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Decision</th></tr></thead><tbody class="divide-y">@forelse($requests as $pr)<tr><td class="px-4 py-3 font-medium">{{ $pr->request_no }}</td><td class="px-4 py-3">@foreach($pr->items as $line)<div>{{ $line->item?->name }} — {{ number_format((float)$line->quantity,2) }}</div>@endforeach</td><td class="px-4 py-3 capitalize">{{ str_replace('_',' ',$pr->status) }}</td><td class="px-4 py-3">@if($pr->status==='pending')<form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/purchase-requests/'.$pr->id.'/decision') }}" class="flex flex-wrap gap-2">@csrf<input name="decision_note" placeholder="Decision note" class="w-32 rounded border-gray-300"><button name="status" value="approved" class="rounded bg-green-700 px-2 py-1 text-white">Approve</button><button name="status" value="rejected" class="rounded bg-red-700 px-2 py-1 text-white">Reject</button></form>@else{{ $pr->decision_note ?? '—' }}@endif</td></tr>@empty<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No purchase requests.</td></tr>@endforelse</tbody></table></div></section>
<section class="mb-6 overflow-hidden rounded-lg bg-white shadow"><div class="border-b p-5"><h4 class="font-bold">Purchase orders and goods receiving</h4></div><div class="divide-y">
@forelse($orders as $order)<div class="p-5"><div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div><strong>{{ $order->order_no }}</strong> · {{ $order->supplier?->name }}<p class="text-sm text-gray-500">Status: {{ str_replace('_',' ',$order->status) }}</p></div><span class="text-sm">{{ $order->items->sum('quantity_received') }} units received</span></div><ul class="mb-3 list-disc pl-5 text-sm">@foreach($order->items as $line)<li>{{ $line->item?->name }}: {{ $line->quantity_received }} / {{ $line->quantity_ordered }}</li>@endforeach</ul>
@if(!in_array($order->status,['received','cancelled']))<form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/purchase-orders/'.$order->id.'/receive') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-3">@csrf
<select name="location_id" required class="rounded border-gray-300"><option value="">Put away at location</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select><input type="date" name="received_at" required value="{{ today()->toDateString() }}" class="rounded border-gray-300"><input name="notes" placeholder="Receipt notes" class="rounded border-gray-300">
@foreach($order->items as $line)<label class="text-sm">{{ $line->item?->name }} outstanding: {{ max(0,(float)$line->quantity_ordered-(float)$line->quantity_received) }}<input type="number" name="items[{{ $line->id }}][quantity_received]" min="0" max="{{ max(0,(float)$line->quantity_ordered-(float)$line->quantity_received) }}" step="0.01" placeholder="Received qty" class="mt-1 w-full rounded border-gray-300"></label>@endforeach
<button class="rounded bg-indigo-700 px-4 py-2 text-white sm:col-span-3">Record goods received</button></form>@endif
</div>@empty<div class="p-6 text-center text-gray-500">No purchase orders yet.</div>@endforelse
</div></section>
<div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Register school asset</h4>
        <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/assets') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
            <input name="asset_tag" required placeholder="Asset tag / ID" class="rounded border-gray-300"><input name="name" required placeholder="Asset name" class="rounded border-gray-300">
            <select name="category_id" class="rounded border-gray-300"><option value="">Asset category</option>@foreach($categories->where('category_type','asset') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
            <select name="location_id" class="rounded border-gray-300"><option value="">Location</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
            <select name="supplier_id" class="rounded border-gray-300"><option value="">Supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            <select name="assigned_to" class="rounded border-gray-300"><option value="">Assign to staff / user (optional)</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ str_replace("_"," ",$user->role) }}</option>@endforeach</select>
            <input name="serial_no" placeholder="Serial number" class="rounded border-gray-300"><input type="date" name="purchase_date" class="rounded border-gray-300"><input type="number" name="purchase_cost" min="0" step="0.01" placeholder="Purchase cost ₦" class="rounded border-gray-300"><input type="date" name="warranty_until" class="rounded border-gray-300">
            <select name="condition" class="rounded border-gray-300"><option value="new">New</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option><option value="damaged">Damaged</option></select>
            <input name="description" placeholder="Description" class="rounded border-gray-300"><button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Register asset</button>
        </form>
    </section>
    <section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-4 font-bold">Assets register</h4>
        <div class="max-h-96 space-y-3 overflow-y-auto">@forelse($assets as $asset)<div class="border-b pb-3"><div class="flex justify-between gap-2"><div><strong>{{ $asset->asset_tag }} — {{ $asset->name }}</strong><p class="text-sm text-gray-500">{{ $asset->location?->name ?? 'No location' }} · {{ $asset->condition }}</p></div><span class="text-sm capitalize">{{ str_replace('_',' ',$asset->status) }}</span></div>
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/assets/'.$asset->id.'/status') }}" class="mt-2 flex gap-2">@csrf<select name="status" class="rounded border-gray-300 text-sm"><option value="active">Active</option><option value="under_maintenance">Under maintenance</option><option value="disposed">Disposed</option><option value="lost">Lost</option></select><button class="rounded bg-gray-800 px-3 py-1 text-sm text-white">Update status</button></form>
            <form method="POST" action="{{ url('/school/'.request()->route('school').'/inventory/assets/'.$asset->id.'/maintenance') }}" class="mt-2 grid grid-cols-2 gap-2">@csrf<input name="description" required placeholder="Maintenance work" class="rounded border-gray-300 text-sm"><input type="number" name="cost" min="0" step="0.01" placeholder="Cost ₦" class="rounded border-gray-300 text-sm"><input type="date" name="service_date" class="rounded border-gray-300 text-sm"><input type="date" name="next_service_date" class="rounded border-gray-300 text-sm"><select name="status" class="rounded border-gray-300 text-sm"><option value="scheduled">Scheduled</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select><button class="rounded bg-indigo-700 px-2 py-1 text-sm text-white">Add maintenance</button></form>
        </div>@empty<p class="text-sm text-gray-500">No assets registered yet.</p>@endforelse</div>
    </section>
</div>
<section class="rounded-lg bg-white p-5 shadow"><h4 class="mb-3 font-bold">Recent maintenance records</h4>@forelse($maintenance as $record)<div class="flex flex-wrap justify-between gap-2 border-b py-2 text-sm"><span><strong>{{ $record->asset?->asset_tag }}</strong> — {{ $record->description }}</span><span>{{ $record->status }} · ₦{{ number_format((float)$record->cost,2) }} · {{ $record->service_date?->format('d M Y') ?? 'Date pending' }}</span></div>@empty<p class="text-sm text-gray-500">No maintenance records yet.</p>@endforelse</section>

<section class="mt-6 overflow-hidden rounded-lg bg-white shadow"><div class="border-b p-5"><h4 class="font-bold">Recent stock movement history</h4><p class="text-sm text-gray-500">Latest 30 receipts, issues and stock-count adjustments.</p></div><div class="overflow-x-auto"><table class="min-w-full divide-y text-sm"><thead class="bg-gray-50 text-left"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Item</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Movement</th><th class="px-4 py-3">Quantity change</th><th class="px-4 py-3">Reference / notes</th></tr></thead><tbody class="divide-y">@forelse($movements as $movement)<tr><td class="px-4 py-3">{{ $movement->movement_at?->format('d M Y H:i') }}</td><td class="px-4 py-3">{{ $movement->item?->name }}</td><td class="px-4 py-3">{{ $movement->location?->name }}</td><td class="px-4 py-3 capitalize">{{ $movement->movement_type }}</td><td class="px-4 py-3">{{ number_format((float)$movement->quantity,2) }}</td><td class="px-4 py-3">{{ $movement->reference }} {{ $movement->notes }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No stock movements recorded.</td></tr>@endforelse</tbody></table></div></section>

@endsection
