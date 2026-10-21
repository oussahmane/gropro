<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderIdempotencyKey;
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

        $idempotencyKey = $this->idempotencyKeyFrom($request);
        if ($idempotencyKey !== null) {
            $cached = $this->findIdempotentResponse($order, 'update', $idempotencyKey);
            if ($cached !== null) {
                return response()->json($cached->response_body, $cached->status_code);
            }
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
            'base_revision' => 'nullable|integer|min:0',
        ]);

        $conflict = $this->workerUpdateConflict($order, $validated['base_revision'] ?? null);
        if ($conflict !== null) {
            return $this->storeIdempotentResponse(
                $order,
                'update',
                $idempotencyKey,
                409,
                $conflict
            );
        }

        $payload = DB::transaction(function () use ($order, $validated) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $conflict = $this->workerUpdateConflict($locked, $validated['base_revision'] ?? null);
            if ($conflict !== null) {
                return ['conflict' => $conflict];
            }

            $updateData = [];
            if (array_key_exists('customer_name', $validated)) {
                $updateData['customer_name'] = $validated['customer_name'] ?? '';
            }
            if (array_key_exists('notes', $validated)) {
                $updateData['notes'] = $validated['notes'];
            }
            $updateData['content_revision'] = ((int) $locked->content_revision) + 1;
            $locked->update($updateData);

            $locked->items()->delete();

            if (isset($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    if ($item['box'] == 0 && $item['units'] == 0) {
                        continue;
                    }
                    OrderItem::create([
                        'order_id' => $locked->id,
                        'product_id' => $item['product_id'] ?? null,
                        'product_designation' => $item['designation'],
                        'qtu' => $item['qtu'],
                        'box_quantity' => $item['box'],
                        'units_quantity' => $item['units'],
                    ]);
                }
            }

            $locked->refresh();
            $locked->load('items');

            return [
                'success' => true,
                'content_revision' => (int) $locked->content_revision,
                'updated_at' => optional($locked->updated_at)?->toISOString(),
            ];
        });

        if (isset($payload['conflict'])) {
            return $this->storeIdempotentResponse(
                $order,
                'update',
                $idempotencyKey,
                409,
                $payload['conflict']
            );
        }

        return $this->storeIdempotentResponse($order, 'update', $idempotencyKey, 200, $payload);
    }

    public function submitOrder(Request $request, Order $order)
    {
        if ($order->worker_id !== auth()->id()) {
            abort(403);
        }

        $idempotencyKey = $this->idempotencyKeyFrom($request);
        if ($idempotencyKey !== null) {
            $cached = $this->findIdempotentResponse($order, 'submit', $idempotencyKey);
            if ($cached !== null) {
                return response()->json($cached->response_body, $cached->status_code);
            }
        }

        $validated = $request->validate([
            'base_revision' => 'nullable|integer|min:0',
        ]);

        $result = DB::transaction(function () use ($order, $validated) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->worker_id !== auth()->id()) {
                return [
                    'status' => 403,
                    'body' => ['success' => false, 'message' => 'Forbidden.'],
                ];
            }

            // Already submitted with this outcome — safe retry without re-transition side effects.
            if ($locked->status === 'received') {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'redirect' => route('worker.dashboard'),
                        'content_revision' => (int) $locked->content_revision,
                        'status' => $locked->status,
                    ],
                ];
            }

            if ($locked->status !== 'draft') {
                $locked->load('items');

                return [
                    'status' => 409,
                    'body' => $this->conflictPayload(
                        $locked,
                        'Cette commande ne peut plus être soumise (statut: '.$locked->status.').'
                    ),
                ];
            }

            if ($locked->isClaimedByAnother(auth()->user())) {
                $locked->load('items');

                return [
                    'status' => 409,
                    'body' => $this->conflictPayload(
                        $locked,
                        'Cette commande est gérée par un caissier et ne peut plus être modifiée.'
                    ),
                ];
            }

            if (array_key_exists('base_revision', $validated) && $validated['base_revision'] !== null
                && (int) $validated['base_revision'] !== (int) $locked->content_revision) {
                $locked->load('items');

                return [
                    'status' => 409,
                    'body' => $this->conflictPayload(
                        $locked,
                        'La commande a été modifiée sur le serveur. Synchronisation refusée pour éviter d’écraser des changements plus récents.'
                    ),
                ];
            }

            $locked->update(['status' => 'received']);
            $locked->refresh();

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'redirect' => route('worker.dashboard'),
                    'content_revision' => (int) $locked->content_revision,
                    'status' => $locked->status,
                ],
            ];
        });

        return $this->storeIdempotentResponse(
            $order,
            'submit',
            $idempotencyKey,
            $result['status'],
            $result['body']
        );
    }

    private function idempotencyKeyFrom(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        if (! is_string($key)) {
            return null;
        }
        $key = trim($key);
        if ($key === '' || strlen($key) > 64) {
            return null;
        }

        return $key;
    }

    private function findIdempotentResponse(Order $order, string $endpoint, string $key): ?OrderIdempotencyKey
    {
        return OrderIdempotencyKey::query()
            ->where('order_id', $order->id)
            ->where('user_id', auth()->id())
            ->where('endpoint', $endpoint)
            ->where('key', $key)
            ->first();
    }

    private function storeIdempotentResponse(
        Order $order,
        string $endpoint,
        ?string $key,
        int $status,
        array $body
    ) {
        if ($key !== null) {
            OrderIdempotencyKey::query()->updateOrCreate(
                [
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'endpoint' => $endpoint,
                    'key' => $key,
                ],
                [
                    'status_code' => $status,
                    'response_body' => $body,
                ]
            );
        }

        return response()->json($body, $status);
    }

    private function workerUpdateConflict(Order $order, mixed $baseRevision): ?array
    {
        if ($order->status !== 'draft') {
            $order->loadMissing('items');

            return $this->conflictPayload(
                $order,
                'Cette commande n’est plus un brouillon et ne peut pas être mise à jour.'
            );
        }

        if ($order->isClaimedByAnother(auth()->user())) {
            $order->loadMissing('items');

            return $this->conflictPayload(
                $order,
                'Cette commande est gérée par un caissier et ne peut plus être modifiée.'
            );
        }

        if ($baseRevision !== null && (int) $baseRevision !== (int) $order->content_revision) {
            $order->loadMissing('items');

            return $this->conflictPayload(
                $order,
                'La commande a été modifiée sur le serveur. Synchronisation refusée pour éviter d’écraser des changements plus récents.'
            );
        }

        return null;
    }

    private function conflictPayload(Order $order, string $message): array
    {
        return [
            'success' => false,
            'conflict' => true,
            'message' => $message,
            'content_revision' => (int) $order->content_revision,
            'updated_at' => optional($order->updated_at)?->toISOString(),
            'status' => $order->status,
            'order' => [
                'customer_name' => $order->customer_name,
                'notes' => $order->notes,
                'status' => $order->status,
                'items' => $order->items->map(function (OrderItem $item) {
                    return [
                        'id' => $item->product_id,
                        'designation' => $item->product_designation,
                        'qtu' => $item->qtu,
                        'box' => $item->box_quantity,
                        'units' => $item->units_quantity,
                    ];
                })->values()->all(),
            ],
        ];
    }
}
