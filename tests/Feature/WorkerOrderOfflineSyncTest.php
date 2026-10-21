<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerOrderOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private function worker(): User
    {
        return User::factory()->create(['role' => 'worker', 'active' => true]);
    }

    private function draftOrder(User $worker, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'worker_id' => $worker->id,
            'customer_name' => 'Client',
            'status' => 'draft',
            'content_revision' => 0,
        ], $overrides));
    }

    private function itemPayload(string $name = 'Produit A', int $box = 1, int $units = 12): array
    {
        return [
            'designation' => $name,
            'qtu' => 12,
            'box' => $box,
            'units' => $units,
        ];
    }

    public function test_update_increments_content_revision_and_returns_it(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker);
        $this->actingAs($worker);

        $response = $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Alice',
            'notes' => 'Long order',
            'base_revision' => 0,
            'items' => [
                $this->itemPayload('Item 1', 2, 24),
                $this->itemPayload('Item 2', 1, 12),
                $this->itemPayload('Item 3', 3, 36),
            ],
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'content_revision' => 1,
        ]);

        $order->refresh();
        $this->assertSame(1, (int) $order->content_revision);
        $this->assertSame('Alice', $order->customer_name);
        $this->assertSame(3, $order->items()->count());
    }

    public function test_idempotent_update_retry_does_not_double_apply(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker);
        $this->actingAs($worker);

        $payload = [
            'customer_name' => 'Retry Client',
            'notes' => null,
            'base_revision' => 0,
            'idempotency_key' => 'update-key-1',
            'items' => [$this->itemPayload('Only Once', 5, 60)],
        ];

        $first = $this->postJson(route('worker.order.update', $order), $payload);
        $first->assertOk()->assertJson(['success' => true, 'content_revision' => 1]);

        $second = $this->postJson(route('worker.order.update', $order), $payload);
        $second->assertOk()->assertJson(['success' => true, 'content_revision' => 1]);

        $order->refresh();
        $this->assertSame(1, (int) $order->content_revision);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(5, (int) $order->items()->first()->box_quantity);

        $this->assertSame(1, OrderIdempotencyKey::query()->where('key', 'update-key-1')->count());
    }

    public function test_stale_base_revision_returns_conflict_without_overwrite(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker, ['content_revision' => 2, 'customer_name' => 'Server Wins']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_designation' => 'Server Item',
            'qtu' => 10,
            'box_quantity' => 1,
            'units_quantity' => 10,
        ]);
        $this->actingAs($worker);

        $response = $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Stale Client',
            'base_revision' => 0,
            'idempotency_key' => 'stale-1',
            'items' => [$this->itemPayload('Should Not Land', 9, 99)],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'conflict' => true,
                'content_revision' => 2,
            ]);

        $order->refresh();
        $this->assertSame('Server Wins', $order->customer_name);
        $this->assertSame(2, (int) $order->content_revision);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Server Item', $order->items()->first()->product_designation);
    }

    public function test_update_rejected_when_order_no_longer_draft(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker, ['status' => 'received', 'content_revision' => 1]);
        $this->actingAs($worker);

        $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Too Late',
            'base_revision' => 1,
            'items' => [$this->itemPayload()],
        ])->assertStatus(409)->assertJson(['conflict' => true]);

        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertSame('Client', $order->customer_name);
    }

    public function test_update_rejected_when_claimed_by_another_cashier(): void
    {
        $worker = $this->worker();
        $cashier = User::factory()->create(['role' => 'admin', 'active' => true]);
        $order = $this->draftOrder($worker, [
            'cashier_id' => $cashier->id,
            'claimed_at' => now(),
            'content_revision' => 0,
        ]);
        $this->actingAs($worker);

        $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Blocked',
            'base_revision' => 0,
            'items' => [$this->itemPayload()],
        ])->assertStatus(409)->assertJson(['conflict' => true]);
    }

    public function test_quantity_edit_and_removal_sync_via_update(): void
    {
        $worker = $this->worker();
        $product = Product::create([
            'designation' => 'Catalog Product',
            'qtu' => 6,
        ]);
        $order = $this->draftOrder($worker);
        $this->actingAs($worker);

        $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Qty Client',
            'base_revision' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'designation' => $product->designation,
                    'qtu' => 6,
                    'box' => 2,
                    'units' => 12,
                ],
                $this->itemPayload('Remove Me', 1, 12),
            ],
        ])->assertOk()->assertJson(['content_revision' => 1]);

        $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Qty Client',
            'base_revision' => 1,
            'items' => [
                [
                    'product_id' => $product->id,
                    'designation' => $product->designation,
                    'qtu' => 6,
                    'box' => 4,
                    'units' => 24,
                ],
            ],
        ])->assertOk()->assertJson(['content_revision' => 2]);

        $order->refresh();
        $this->assertSame(1, $order->items()->count());
        $item = $order->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(4, (int) $item->box_quantity);
        $this->assertSame(24, (int) $item->units_quantity);
    }

    public function test_submit_is_idempotent_and_does_not_duplicate_transition(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker, ['content_revision' => 3]);
        $this->actingAs($worker);

        $payload = [
            'base_revision' => 3,
            'idempotency_key' => 'submit-key-1',
        ];

        $first = $this->postJson(route('worker.order.submit', $order), $payload);
        $first->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('worker.dashboard'),
            'status' => 'received',
        ]);

        $second = $this->postJson(route('worker.order.submit', $order), $payload);
        $second->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('worker.dashboard'),
            'status' => 'received',
        ]);

        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertSame(3, (int) $order->content_revision);
        $this->assertSame(1, OrderIdempotencyKey::query()->where('endpoint', 'submit')->count());
    }

    public function test_submit_conflict_when_base_revision_stale(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker, ['content_revision' => 5]);
        $this->actingAs($worker);

        $this->postJson(route('worker.order.submit', $order), [
            'base_revision' => 2,
            'idempotency_key' => 'submit-stale',
        ])->assertStatus(409)->assertJson(['conflict' => true]);

        $order->refresh();
        $this->assertSame('draft', $order->status);
    }

    public function test_multiple_orders_have_independent_revisions_and_keys(): void
    {
        $worker = $this->worker();
        $this->actingAs($worker);

        $order1 = $this->draftOrder($worker);
        $order2 = $this->draftOrder($worker);

        $this->postJson(route('worker.order.update', $order1), [
            'customer_name' => 'A',
            'base_revision' => 0,
            'idempotency_key' => 'shared-looking-key',
            'items' => [$this->itemPayload('A1')],
        ])->assertOk()->assertJson(['content_revision' => 1]);

        $this->postJson(route('worker.order.update', $order2), [
            'customer_name' => 'B',
            'base_revision' => 0,
            'idempotency_key' => 'shared-looking-key',
            'items' => [$this->itemPayload('B1')],
        ])->assertOk()->assertJson(['content_revision' => 1]);

        $order1->refresh();
        $order2->refresh();

        $this->assertSame('A', $order1->customer_name);
        $this->assertSame('B', $order2->customer_name);
        $this->assertSame(2, OrderIdempotencyKey::query()->where('key', 'shared-looking-key')->count());
    }

    public function test_edit_page_includes_offline_sync_assets_and_status(): void
    {
        $worker = $this->worker();
        $order = $this->draftOrder($worker);
        $this->actingAs($worker);

        $page = $this->get(route('worker.order.edit', $order));
        $page->assertOk();
        $page->assertSee('assets/js/worker-order-offline.js', false);
        $page->assertSee('id="orderSyncStatus"', false);
        $page->assertSee('GroproWorkerOrderOffline', false);
        $page->assertSee('contentRevision', false);
    }

    public function test_other_worker_cannot_update_order(): void
    {
        $owner = $this->worker();
        $other = $this->worker();
        $order = $this->draftOrder($owner);
        $this->actingAs($other);

        $this->postJson(route('worker.order.update', $order), [
            'customer_name' => 'Hacker',
            'base_revision' => 0,
            'items' => [$this->itemPayload()],
        ])->assertForbidden();
    }
}
