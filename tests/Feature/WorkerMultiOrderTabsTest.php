<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-order via separate browser tabs (URL-scoped /order/{id}/edit), not an in-app tab bar.
 */
class WorkerMultiOrderTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_create_redirects_to_a_distinct_edit_url(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $this->actingAs($worker);

        $first = $this->get(route('worker.order.create'));
        $order1 = Order::latest('id')->first();
        $first->assertRedirect(route('worker.order.edit', $order1));

        $second = $this->get(route('worker.order.create'));
        $order2 = Order::latest('id')->first();
        $second->assertRedirect(route('worker.order.edit', $order2));

        $this->assertNotEquals($order1->id, $order2->id);
        $this->assertNotEquals(
            route('worker.order.edit', $order1),
            route('worker.order.edit', $order2)
        );
    }

    public function test_edit_page_offers_new_order_in_new_browser_tab_link(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $this->actingAs($worker);

        $order = Order::create([
            'order_number' => 'ORD-BROWSER1',
            'worker_id' => $worker->id,
            'customer_name' => 'Client A',
            'status' => 'draft',
        ]);

        $response = $this->get(route('worker.order.edit', $order));
        $response->assertOk();
        $response->assertDontSee('order-tabs-bar', false);
        $response->assertSee('id="newOrderBrowserTabBtn"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee(route('worker.order.create'), false);
    }

    public function test_updates_and_submit_are_isolated_per_order_id(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $this->actingAs($worker);

        $this->get(route('worker.order.create'));
        $order1 = Order::latest('id')->first();
        $this->get(route('worker.order.create'));
        $order2 = Order::latest('id')->first();

        $this->post(route('worker.order.update', $order1), [
            'customer_name' => 'Alice',
            'notes' => 'Browser tab A',
            'items' => [
                [
                    'designation' => 'Product A',
                    'qtu' => 12,
                    'box' => 2,
                    'units' => 24,
                ],
            ],
        ])->assertJson(['success' => true]);

        $this->post(route('worker.order.update', $order2), [
            'customer_name' => 'Bob',
            'notes' => 'Browser tab B',
            'items' => [
                [
                    'designation' => 'Product B',
                    'qtu' => 6,
                    'box' => 1,
                    'units' => 6,
                ],
            ],
        ])->assertJson(['success' => true]);

        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('Alice', $order1->customer_name);
        $this->assertEquals(1, $order1->items()->count());
        $this->assertEquals('Product A', $order1->items()->first()->product_designation);

        $this->assertEquals('Bob', $order2->customer_name);
        $this->assertEquals(1, $order2->items()->count());
        $this->assertEquals('Product B', $order2->items()->first()->product_designation);

        $this->postJson(route('worker.order.submit', $order1))
            ->assertJson([
                'success' => true,
                'redirect' => route('worker.dashboard'),
            ]);

        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('received', $order1->status);
        $this->assertEquals('draft', $order2->status);
        $this->assertEquals('Bob', $order2->customer_name);
        $this->assertEquals(1, $order2->items()->count());
    }

    public function test_edit_page_loads_only_that_order_items(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $this->actingAs($worker);

        $orderA = Order::create([
            'order_number' => 'ORD-ONLYA',
            'worker_id' => $worker->id,
            'customer_name' => 'Only A',
            'status' => 'draft',
        ]);
        $orderB = Order::create([
            'order_number' => 'ORD-ONLYB',
            'worker_id' => $worker->id,
            'customer_name' => 'Only B',
            'status' => 'draft',
        ]);

        OrderItem::create([
            'order_id' => $orderA->id,
            'product_id' => null,
            'product_designation' => 'Item For A Only',
            'qtu' => 10,
            'box_quantity' => 1,
            'units_quantity' => 10,
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'product_id' => null,
            'product_designation' => 'Item For B Only',
            'qtu' => 5,
            'box_quantity' => 2,
            'units_quantity' => 10,
        ]);

        $pageA = $this->get(route('worker.order.edit', $orderA));
        $pageA->assertOk();
        $pageA->assertSee('Item For A Only', false);
        $pageA->assertDontSee('Item For B Only', false);
        $pageA->assertSee('Only A', false);

        $pageB = $this->get(route('worker.order.edit', $orderB));
        $pageB->assertOk();
        $pageB->assertSee('Item For B Only', false);
        $pageB->assertDontSee('Item For A Only', false);
    }
}
