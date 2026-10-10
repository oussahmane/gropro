<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProductsCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_order_edit_page_shows_order_items_count(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $product = Product::create(['designation' => 'Product A', 'qtu' => 6]);

        $order = Order::create([
            'order_number' => 'ORD-TEST1234',
            'worker_id' => $worker->id,
            'customer_name' => '',
            'status' => 'draft',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_designation' => $product->designation,
            'qtu' => $product->qtu,
            'box_quantity' => 1,
            'units_quantity' => $product->qtu,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_designation' => 'Custom Line',
            'qtu' => 1,
            'box_quantity' => 2,
            'units_quantity' => 2,
        ]);

        $response = $this->actingAs($worker)->get(route('worker.order.edit', $order));

        $response->assertOk();
        $response->assertSee('id="order-items-count"', false);
        $response->assertSee('2 produit(s) dans cette commande', false);
        $response->assertDontSee('Produits dans le catalogue', false);
        $response->assertDontSee('id="global-products-count"', false);
    }

    public function test_admin_order_show_page_shows_order_items_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $worker = User::factory()->create(['role' => 'worker']);
        $product = Product::create(['designation' => 'Product B', 'qtu' => 12]);

        $order = Order::create([
            'order_number' => 'ORD-ADMIN99',
            'worker_id' => $worker->id,
            'customer_name' => 'Client Test',
            'status' => 'received',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_designation' => $product->designation,
            'qtu' => $product->qtu,
            'box_quantity' => 1,
            'units_quantity' => $product->qtu,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee('id="order-items-count"', false);
        $response->assertSee('1 produit(s) dans cette commande', false);
        $response->assertDontSee('Produits dans le catalogue', false);
    }
}
