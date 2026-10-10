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

    public function test_worker_order_page_shows_order_line_count_not_catalogue_size(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        // M products in the catalogue
        for ($i = 1; $i <= 100; $i++) {
            Product::create(['designation' => "Catalogue Product {$i}", 'qtu' => 6]);
        }

        $order = Order::create([
            'order_number' => 'ORD-TEST1234',
            'worker_id' => $worker->id,
            'customer_name' => '',
            'status' => 'draft',
        ]);

        // N lines on THIS order only
        foreach ([1, 2, 3] as $i) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => null,
                'product_designation' => "Order Line {$i}",
                'qtu' => 1,
                'box_quantity' => 1,
                'units_quantity' => 1,
            ]);
        }

        $this->assertSame(100, Product::count());
        $this->assertSame(3, $order->items()->count());

        $response = $this->actingAs($worker)->get(route('worker.order.edit', $order));

        $response->assertOk();
        $response->assertViewHas('orderItemsCount', 3);
        $response->assertSee('id="order-items-count"', false);
        $response->assertSee('data-order-items-count="3"', false);
        $response->assertSee('3 article(s) dans cette commande', false);
        $response->assertDontSee('100 article(s) dans cette commande', false);
        $response->assertDontSee('Produits dans le catalogue', false);
        $response->assertDontSee('id="global-products-count"', false);
    }

    public function test_admin_order_show_page_shows_order_line_count_not_catalogue_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $worker = User::factory()->create(['role' => 'worker']);

        for ($i = 1; $i <= 50; $i++) {
            Product::create(['designation' => "Stock {$i}", 'qtu' => 12]);
        }

        $order = Order::create([
            'order_number' => 'ORD-ADMIN99',
            'worker_id' => $worker->id,
            'customer_name' => 'Client Test',
            'status' => 'received',
        ]);

        foreach ([1, 2] as $i) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => null,
                'product_designation' => "Line {$i}",
                'qtu' => 1,
                'box_quantity' => 1,
                'units_quantity' => 1,
            ]);
        }

        $this->assertSame(50, Product::count());
        $this->assertSame(2, $order->items()->count());

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertViewHas('orderItemsCount', 2);
        $response->assertSee('data-order-items-count="2"', false);
        $response->assertSee('2 article(s) dans cette commande', false);
        $response->assertDontSee('50 article(s) dans cette commande', false);
        $response->assertDontSee('Produits dans le catalogue', false);
    }
}
