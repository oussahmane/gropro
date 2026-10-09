<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkerController extends Controller
{
    public function dashboard()
    {
        $orders = Order::where('worker_id', auth()->id())->orderBy('created_at', 'desc')->get();
        return view('worker.dashboard', compact('orders'));
    }

    public function createOrder()
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'worker_id' => auth()->id(),
            'customer_name' => '',
            'status' => 'draft'
        ]);

        return redirect()->route('worker.order.edit', $order);
    }

    public function searchProducts(Request $request)
    {
        $query = $request->get('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::whereRaw('LOWER(designation) LIKE ?', ['%' . strtolower($query) . '%'])
            ->limit(20)
            ->get(['id', 'designation', 'qtu']);

        return response()->json($products);
    }

    public function editOrder(Order $order)
    {
        if ($order->worker_id !== auth()->id()) {
            abort(403);
        }
        
        $order->load('items');
        return view('worker.order.create', compact('order'));
    }

    public function updateOrder(Request $request, Order $order)
    {
        if ($order->worker_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.designation' => 'required|string|max:255',
            'items.*.qtu' => 'required|integer|min:1',
            'items.*.box' => 'required|integer|min:0',
            'items.*.units' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($order, $validated) {
            $updateData = [];
            if (array_key_exists('customer_name', $validated)) {
                $updateData['customer_name'] = $validated['customer_name'] ?? '';
            }
            if (array_key_exists('notes', $validated)) {
                $updateData['notes'] = $validated['notes'];
            }
            if (!empty($updateData)) {
                $order->update($updateData);
            }

            $order->items()->delete();

            if (isset($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if ($item['box'] == 0 && $item['units'] == 0) continue;
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'] ?? null,
                        'product_designation' => $item['designation'],
                        'qtu' => $item['qtu'],
                        'box_quantity' => $item['box'],
                        'units_quantity' => $item['units'],
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function submitOrder(Request $request, Order $order)
    {
        if ($order->worker_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status === 'draft') {
            $order->update(['status' => 'received']);
        }

        return response()->json(['success' => true, 'redirect' => route('worker.dashboard')]);
    }
}
