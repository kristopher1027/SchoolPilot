<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetMaintenance;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\GoodsReceiptItem;
use App\Models\Tenant\InventoryCategory;
use App\Models\Tenant\InventoryItem;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\InventoryStock;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\PurchaseRequest;
use App\Models\Tenant\PurchaseRequestItem;
use App\Models\Tenant\StockLocation;
use App\Models\Tenant\User;
use App\Models\Tenant\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::with(['category', 'stocks.location'])->orderBy('name')->get();
        $categories = InventoryCategory::orderBy('name')->get();
        $locations = StockLocation::orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $requests = PurchaseRequest::with(['items.item', 'items'])->latest()->limit(20)->get();
        $orders = PurchaseOrder::with(['supplier', 'items.item'])->latest()->limit(20)->get();
        $assets = Asset::with(['category', 'location', 'maintenance'])->orderBy('name')->get();
        $maintenance = AssetMaintenance::with('asset')->latest()->limit(20)->get();
        $movements = InventoryMovement::with(['item', 'location'])->latest('movement_at')->limit(30)->get();
        $users = User::where('is_active', true)->whereNotIn('role', ['student', 'parent'])->orderBy('name')->get();

        $stockRows = InventoryStock::with('item')->get();
        $lowStock = $items->filter(fn ($item) => (float) $item->stocks->sum('quantity') <= (float) $item->reorder_level);
        $summary = [
            'items' => $items->count(),
            'low_stock' => $lowStock->count(),
            'assets' => $assets->where('status', 'active')->count(),
            'pending_requests' => PurchaseRequest::where('status', 'pending')->count(),
        ];

        return view('tenant.inventory.index', compact(
            'items', 'categories', 'locations', 'suppliers', 'requests', 'orders',
            'assets', 'maintenance', 'movements', 'users', 'lowStock', 'summary'
        ));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('inventory_categories', 'name')->where(fn ($query) => $query->where('category_type', $request->input('category_type')))],
            'category_type' => ['required', Rule::in(['stock', 'asset'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        InventoryCategory::create($data);
        return $this->back($request, 'Category created.');
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:stock_locations,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        StockLocation::create($data);
        return $this->back($request, 'Stock location created.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        Supplier::create($data);
        return $this->back($request, 'Supplier saved.');
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80', 'unique:inventory_items,sku'],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where('category_type', 'stock')],
            'description' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', 'max:40'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ]);
        InventoryItem::create($data);
        return $this->back($request, 'Inventory item created. Record opening stock with a stock adjustment.');
    }

    public function adjustStock(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'movement_type' => ['required', Rule::in(['receipt', 'issue', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['movement_type'] !== 'adjustment' && (float) $data['quantity'] <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Receipts and issues must be greater than zero.']);
        }

        DB::transaction(function () use ($data) {
            $stock = InventoryStock::where('item_id', $data['item_id'])
                ->where('location_id', $data['location_id'])->lockForUpdate()->first();
            $current = (float) ($stock?->quantity ?? 0);
            $quantity = (float) $data['quantity'];
            $next = match ($data['movement_type']) {
                'receipt' => $current + $quantity,
                'issue' => $current - $quantity,
                default => $quantity,
            };
            if ($next < 0) {
                throw ValidationException::withMessages(['quantity' => 'The issue quantity exceeds available stock at this location.']);
            }
            if (!$stock) {
                $stock = InventoryStock::create(['item_id' => $data['item_id'], 'location_id' => $data['location_id'], 'quantity' => 0]);
            }
            $stock->update(['quantity' => $next]);
            InventoryMovement::create([
                'item_id' => $data['item_id'], 'location_id' => $data['location_id'],
                'movement_type' => $data['movement_type'], 'quantity' => $data['movement_type'] === 'adjustment' ? $next - $current : $quantity,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'recorded_by' => Auth::guard('school')->id(), 'movement_at' => now(),
            ]);
            $this->audit('inventory.stock_' . $data['movement_type'], 'inventory_item', (int) $data['item_id'], ['quantity' => $current], ['quantity' => $next, 'location_id' => $data['location_id']]);
        });
        return $this->back($request, 'Stock movement recorded.');
    }

    public function requestPurchase(Request $request)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => ['nullable', 'integer', 'distinct', 'exists:inventory_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $lines = collect($data['items'])->filter(fn ($line) => !empty($line['item_id']))->values();
        if ($lines->isEmpty() || $lines->contains(fn ($line) => empty($line['quantity']))) {
            throw ValidationException::withMessages(['items' => 'Add at least one item and quantity to the purchase request.']);
        }
        DB::transaction(function () use ($data, $lines) {
            $request = PurchaseRequest::create([
                'request_no' => $this->number('PR'), 'requested_by' => Auth::guard('school')->id(),
                'status' => 'pending', 'reason' => $data['reason'] ?? null,
            ]);
            foreach ($lines as $item) {
                $request->items()->create($item);
            }
            $this->audit('purchase_request.created', 'purchase_request', $request->id, null, ['request_no' => $request->request_no]);
        });
        return $this->back($request, 'Purchase request submitted for approval.');
    }

    public function decidePurchaseRequest(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($purchaseRequest->status === 'pending', 422, 'This request has already been reviewed.');
        abort_if((int) $purchaseRequest->requested_by === (int) Auth::guard('school')->id(), 403, 'The requester cannot approve or reject their own purchase request.');
        $purchaseRequest->update([
            'status' => $data['status'], 'approved_by' => Auth::guard('school')->id(),
            'approved_at' => now(), 'decision_note' => $data['decision_note'] ?? null,
        ]);
        $this->audit('purchase_request.' . $data['status'], 'purchase_request', $purchaseRequest->id, ['status' => 'pending'], ['status' => $data['status']]);
        return $this->back($request, 'Purchase request ' . $data['status'] . '.');
    }

    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => ['nullable', 'integer', 'distinct', 'exists:inventory_items,id'],
            'items.*.quantity_ordered' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $lines = collect($data['items'])->filter(fn ($line) => !empty($line['item_id']))->values();
        if ($lines->isEmpty() || $lines->contains(fn ($line) => !isset($line['quantity_ordered'], $line['unit_cost']))) {
            throw ValidationException::withMessages(['items' => 'Add at least one item with a quantity and unit cost to the purchase order.']);
        }
        if (!empty($data['purchase_request_id'])) {
            $approved = PurchaseRequest::whereKey($data['purchase_request_id'])->where('status', 'approved')->exists();
            if (!$approved) {
                throw ValidationException::withMessages(['purchase_request_id' => 'Only an approved purchase request can be linked to an order.']);
            }
        }
        DB::transaction(function () use ($data, $lines) {
            $order = PurchaseOrder::create([
                'order_no' => $this->number('PO'), 'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'created_by' => Auth::guard('school')->id(), 'status' => 'ordered',
                'ordered_at' => today(), 'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($lines as $item) {
                $order->items()->create($item);
            }
            $this->audit('purchase_order.created', 'purchase_order', $order->id, null, ['order_no' => $order->order_no]);
        });
        return $this->back($request, 'Purchase order created.');
    }

    public function receiveOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        DB::transaction(function () use ($data, $purchaseOrder) {
            $order = PurchaseOrder::with('items.item')->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($order->status, ['received', 'cancelled'], true), 422, 'This order cannot receive more goods.');
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id, 'received_by' => Auth::guard('school')->id(),
                'receipt_no' => $this->number('GRN'), 'received_at' => $data['received_at'], 'notes' => $data['notes'] ?? null,
            ]);
            $receivedAny = false;
            foreach ($order->items as $line) {
                $qty = (float) ($data['items'][$line->id]['quantity_received'] ?? 0);
                if ($qty <= 0) continue;
                $remaining = (float) $line->quantity_ordered - (float) $line->quantity_received;
                abort_if($qty > $remaining, 422, 'Received quantity exceeds the outstanding quantity for ' . $line->item?->name . '.');
                $line->update(['quantity_received' => (float) $line->quantity_received + $qty]);
                $stock = InventoryStock::firstOrCreate(
                    ['item_id' => $line->item_id, 'location_id' => $data['location_id']],
                    ['quantity' => 0]
                );
                $stock->update(['quantity' => (float) $stock->quantity + $qty]);
                InventoryMovement::create([
                    'item_id' => $line->item_id, 'location_id' => $data['location_id'], 'movement_type' => 'receipt',
                    'quantity' => $qty, 'reference' => $receipt->receipt_no, 'notes' => 'Goods received against ' . $order->order_no,
                    'recorded_by' => Auth::guard('school')->id(), 'movement_at' => now(),
                ]);
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $line->id,
                    'location_id' => $data['location_id'], 'quantity_received' => $qty,
                ]);
                $receivedAny = true;
            }
            abort_unless($receivedAny, 422, 'Enter at least one quantity received.');
            $complete = $order->items->every(fn ($line) => (float) $line->quantity_received >= (float) $line->quantity_ordered);
            $order->update(['status' => $complete ? 'received' : 'partially_received', 'received_at' => $complete ? $data['received_at'] : null]);
            $this->audit('purchase_order.goods_received', 'purchase_order', $order->id, null, ['receipt_no' => $receipt->receipt_no, 'status' => $order->status]);
        });
        return $this->back($request, 'Goods receipt recorded and stock updated.');
    }

    public function storeAsset(Request $request)
    {
        $data = $request->validate([
            'asset_tag' => ['required', 'string', 'max:80', 'unique:assets,asset_tag'],
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where('category_type', 'asset')],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNotIn('role', ['student', 'parent'])],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'condition' => ['required', Rule::in(['new', 'good', 'fair', 'poor', 'damaged'])],
        ]);
        Asset::create($data + ['created_by' => Auth::guard('school')->id()]);
        return $this->back($request, 'Asset registered.');
    }

    public function updateAssetStatus(Request $request, Asset $asset)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'under_maintenance', 'disposed', 'lost'])]]);
        $old = $asset->status;
        $asset->update($data);
        $this->audit('asset.status_updated', 'asset', $asset->id, ['status' => $old], ['status' => $asset->status]);
        return $this->back($request, 'Asset status updated.');
    }

    public function storeMaintenance(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:3000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'service_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date', 'after_or_equal:service_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
        ]);
        DB::transaction(function () use ($asset, $data) {
            AssetMaintenance::create($data + ['asset_id' => $asset->id, 'reported_by' => Auth::guard('school')->id()]);
            if (in_array($data['status'], ['scheduled', 'in_progress'], true)) {
                $asset->update(['status' => 'under_maintenance']);
            } elseif ($data['status'] === 'completed' && $asset->status === 'under_maintenance') {
                $hasOpenWork = $asset->maintenance()->whereIn('status', ['scheduled', 'in_progress'])->exists();
                if (! $hasOpenWork) {
                    $asset->update(['status' => 'active']);
                }
            }
            $this->audit('asset.maintenance_recorded', 'asset', $asset->id, null, ['status' => $data['status'], 'cost' => $data['cost'] ?? 0]);
        });
        return $this->back($request, 'Maintenance record saved.');
    }

    private function number(string $prefix): string
    {
        do { $value = $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)); }
        while (match ($prefix) {
            'PR' => PurchaseRequest::where('request_no', $value)->exists(),
            'PO' => PurchaseOrder::where('order_no', $value)->exists(),
            default => GoodsReceipt::where('receipt_no', $value)->exists(),
        });
        return $value;
    }

    private function audit(string $action, string $type, int $id, ?array $old, ?array $new): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::guard('school')->id(), 'action' => $action,
            'entity_type' => $type, 'entity_id' => $id,
            'old_value' => $old ? json_encode($old) : null, 'new_value' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function back(Request $request, string $message)
    {
        return redirect('/school/' . $request->route('school') . '/inventory')->with('success', $message);
    }
}
