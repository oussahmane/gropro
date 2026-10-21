<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashierOrderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeCashier(string $name = 'Cashier'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => 'admin',
            'active' => true,
        ]);
    }

    private function makeWorker(): User
    {
        return User::factory()->create([
            'role' => 'worker',
            'active' => true,
        ]);
    }

    private function makeOrder(User $worker, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'worker_id' => $worker->id,
            'customer_name' => 'Client Test',
            'status' => 'received',
            'cashier_id' => null,
            'claimed_at' => null,
        ], $overrides));
    }

    public function test_opening_available_order_claims_it_for_cashier(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order = $this->makeOrder($worker);

        $this->actingAs($cashier)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('You are handling this order.', false);

        $order->refresh();
        $this->assertEquals($cashier->id, $order->cashier_id);
        $this->assertNotNull($order->claimed_at);
    }

    public function test_second_cashier_cannot_claim_or_edit_already_claimed_order(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $order = $this->makeOrder($worker);

        $this->actingAs($cashierA)
            ->get(route('admin.orders.show', $order))
            ->assertOk();

        $this->actingAs($cashierB)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('This order is being handled by another cashier.', false)
            ->assertDontSee('name="status"', false);

        $order->refresh();
        $this->assertEquals($cashierA->id, $order->cashier_id);

        $this->actingAs($cashierB)
            ->post(route('admin.orders.update-status', $order), ['status' => 'processing'])
            ->assertRedirect()
            ->assertSessionHas('error', 'This order is being handled by another cashier.');

        $order->refresh();
        $this->assertEquals('received', $order->status);
        $this->assertEquals($cashierA->id, $order->cashier_id);
    }

    public function test_simultaneous_claims_only_one_cashier_wins(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $order = $this->makeOrder($worker);

        $results = [];

        DB::transaction(function () use ($order, $cashierA, &$results) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $results['a'] = $locked->claimFor($cashierA);
        });

        DB::transaction(function () use ($order, $cashierB, &$results) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $results['b'] = $locked->claimFor($cashierB);
        });

        $order->refresh();

        $this->assertTrue($results['a']);
        $this->assertFalse($results['b']);
        $this->assertEquals($cashierA->id, $order->cashier_id);

        // Direct concurrent-style race on a fresh order: both attempt claimFor under row lock.
        $order2 = $this->makeOrder($worker);
        $winnerIds = [];

        $claimA = $order2->claimFor($cashierA);
        $claimB = $order2->claimFor($cashierB);

        $order2->refresh();
        $this->assertTrue($claimA);
        $this->assertFalse($claimB);
        $this->assertEquals($cashierA->id, $order2->cashier_id);
        $winnerIds[] = $order2->cashier_id;
        $this->assertCount(1, array_unique($winnerIds));
    }

    public function test_refresh_keeps_claim_for_owner(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order = $this->makeOrder($worker);

        $this->actingAs($cashier)->get(route('admin.orders.show', $order))->assertOk();
        $claimedAt = $order->fresh()->claimed_at;

        $this->actingAs($cashier)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('You are handling this order.', false)
            ->assertSee('name="status"', false);

        $order->refresh();
        $this->assertEquals($cashier->id, $order->cashier_id);
        $this->assertEquals($claimedAt?->timestamp, $order->claimed_at?->timestamp);
    }

    public function test_completion_releases_claim(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order = $this->makeOrder($worker);

        $this->actingAs($cashier)->get(route('admin.orders.show', $order))->assertOk();
        $this->assertEquals($cashier->id, $order->fresh()->cashier_id);

        $this->actingAs($cashier)
            ->post(route('admin.orders.update-status', $order), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertNull($order->cashier_id);
        $this->assertNull($order->claimed_at);
    }

    public function test_cancellation_releases_claim(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order = $this->makeOrder($worker, [
            'cashier_id' => $cashier->id,
            'claimed_at' => now(),
            'status' => 'processing',
        ]);

        $this->actingAs($cashier)
            ->post(route('admin.orders.update-status', $order), ['status' => 'cancelled'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertNull($order->cashier_id);
    }

    public function test_admin_can_release_abandoned_claim(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $order = $this->makeOrder($worker, [
            'cashier_id' => $cashierA->id,
            'claimed_at' => now()->subHours(2),
        ]);

        $this->actingAs($cashierB)
            ->post(route('admin.orders.release-claim', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNull($order->cashier_id);
        $this->assertNull($order->claimed_at);

        $this->actingAs($cashierB)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('You are handling this order.', false);

        $this->assertEquals($cashierB->id, $order->fresh()->cashier_id);
    }

    public function test_admin_can_reassign_abandoned_order(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $order = $this->makeOrder($worker, [
            'cashier_id' => $cashierA->id,
            'claimed_at' => now()->subHours(2),
        ]);

        $this->actingAs($cashierB)
            ->post(route('admin.orders.reassign-claim', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals($cashierB->id, $order->cashier_id);

        $this->actingAs($cashierB)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('name="status"', false);

        $this->actingAs($cashierA)
            ->post(route('admin.orders.update-status', $order), ['status' => 'processing'])
            ->assertSessionHas('error', 'This order is being handled by another cashier.');
    }

    public function test_worker_workflows_unaffected_by_cashier_claim(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');

        $this->actingAs($worker)
            ->get(route('worker.order.create'))
            ->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertNull($order->cashier_id);

        $this->actingAs($cashier)->get(route('admin.orders.show', $order))->assertOk();
        $this->assertEquals($cashier->id, $order->fresh()->cashier_id);

        $this->actingAs($worker)
            ->get(route('worker.order.edit', $order))
            ->assertOk();

        $this->assertEquals($cashier->id, $order->fresh()->cashier_id);
    }

    public function test_destroy_blocked_when_claimed_by_another_cashier(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $order = $this->makeOrder($worker, [
            'cashier_id' => $cashierA->id,
            'claimed_at' => now(),
        ]);

        $this->actingAs($cashierB)
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect()
            ->assertSessionHas('error', 'This order is being handled by another cashier.');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_cashier_can_claim_and_edit_multiple_orders_at_once(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order1 = $this->makeOrder($worker);
        $order2 = $this->makeOrder($worker);
        $order3 = $this->makeOrder($worker);

        $this->actingAs($cashier)->get(route('admin.orders.show', $order1))->assertOk();
        $this->actingAs($cashier)->get(route('admin.orders.show', $order2))->assertOk();
        $this->actingAs($cashier)->get(route('admin.orders.show', $order3))->assertOk();

        $order1->refresh();
        $order2->refresh();
        $order3->refresh();

        $this->assertEquals($cashier->id, $order1->cashier_id);
        $this->assertEquals($cashier->id, $order2->cashier_id);
        $this->assertEquals($cashier->id, $order3->cashier_id);

        // Claiming a second/third order must not release earlier claims.
        $this->assertNotNull($order1->claimed_at);
        $this->assertNotNull($order2->claimed_at);
        $this->assertNotNull($order3->claimed_at);

        $this->actingAs($cashier)
            ->post(route('admin.orders.update-status', $order1), ['status' => 'processing'])
            ->assertSessionHas('success');
        $this->actingAs($cashier)
            ->post(route('admin.orders.update-status', $order2), ['status' => 'processing'])
            ->assertSessionHas('success');

        $this->assertEquals('processing', $order1->fresh()->status);
        $this->assertEquals('processing', $order2->fresh()->status);
        $this->assertEquals($cashier->id, $order1->fresh()->cashier_id);
        $this->assertEquals($cashier->id, $order2->fresh()->cashier_id);
        $this->assertEquals($cashier->id, $order3->fresh()->cashier_id);
    }

    public function test_completing_one_claimed_order_leaves_other_claims_intact(): void
    {
        $worker = $this->makeWorker();
        $cashier = $this->makeCashier('Alice');
        $order1 = $this->makeOrder($worker);
        $order2 = $this->makeOrder($worker);

        $this->actingAs($cashier)->get(route('admin.orders.show', $order1))->assertOk();
        $this->actingAs($cashier)->get(route('admin.orders.show', $order2))->assertOk();

        $this->actingAs($cashier)
            ->post(route('admin.orders.update-status', $order1), ['status' => 'completed'])
            ->assertSessionHas('success');

        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('completed', $order1->status);
        $this->assertNull($order1->cashier_id);
        $this->assertEquals($cashier->id, $order2->cashier_id);
        $this->assertEquals('received', $order2->status);

        $this->actingAs($cashier)
            ->get(route('admin.orders.show', $order2))
            ->assertOk()
            ->assertSee('You are handling this order.', false)
            ->assertSee('name="status"', false);
    }

    public function test_multiple_orders_across_tabs_keep_independent_ownership(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');
        $aliceOrder1 = $this->makeOrder($worker);
        $aliceOrder2 = $this->makeOrder($worker);
        $bobOrder = $this->makeOrder($worker);

        $this->actingAs($cashierA)->get(route('admin.orders.show', $aliceOrder1))->assertOk();
        $this->actingAs($cashierA)->get(route('admin.orders.show', $aliceOrder2))->assertOk();
        $this->actingAs($cashierB)->get(route('admin.orders.show', $bobOrder))->assertOk();

        // Cross-cashier denial on Alice's second tab order.
        $this->actingAs($cashierB)
            ->get(route('admin.orders.show', $aliceOrder2))
            ->assertOk()
            ->assertSee('This order is being handled by another cashier.', false);

        $this->actingAs($cashierB)
            ->post(route('admin.orders.update-status', $aliceOrder1), ['status' => 'processing'])
            ->assertSessionHas('error');

        // Alice can still work both of hers after Bob's failed attempts.
        $this->actingAs($cashierA)
            ->get(route('admin.orders.show', $aliceOrder1))
            ->assertOk()
            ->assertSee('name="status"', false);
        $this->actingAs($cashierA)
            ->get(route('admin.orders.show', $aliceOrder2))
            ->assertOk()
            ->assertSee('name="status"', false);

        $this->assertEquals($cashierA->id, $aliceOrder1->fresh()->cashier_id);
        $this->assertEquals($cashierA->id, $aliceOrder2->fresh()->cashier_id);
        $this->assertEquals($cashierB->id, $bobOrder->fresh()->cashier_id);
    }

    public function test_abandoned_recovery_does_not_strip_cashiers_other_claims(): void
    {
        $worker = $this->makeWorker();
        $cashierA = $this->makeCashier('Alice');
        $cashierB = $this->makeCashier('Bob');

        $abandoned = $this->makeOrder($worker, [
            'cashier_id' => $cashierA->id,
            'claimed_at' => now()->subHours(3),
        ]);
        $aliceOther = $this->makeOrder($worker, [
            'cashier_id' => $cashierA->id,
            'claimed_at' => now(),
        ]);
        $bobOther = $this->makeOrder($worker, [
            'cashier_id' => $cashierB->id,
            'claimed_at' => now(),
        ]);

        $this->actingAs($cashierB)
            ->post(route('admin.orders.reassign-claim', $abandoned))
            ->assertRedirect(route('admin.orders.show', $abandoned));

        $this->assertEquals($cashierB->id, $abandoned->fresh()->cashier_id);
        $this->assertEquals($cashierA->id, $aliceOther->fresh()->cashier_id);
        $this->assertEquals($cashierB->id, $bobOther->fresh()->cashier_id);
    }
}
