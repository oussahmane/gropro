<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProductsCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_order_edit_page_shows_global_products_count(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $this->seedProducts(3);

        $order = Order::create([
            'order_number' => 'ORD-TEST1234',
            'worker_id' => $worker->id,
            'customer_name' => '',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($worker)->get(route('worker.order.edit', $order));

        $response->assertOk();
        $response->assertViewHas('productsCount', 3);
        $response->assertSee('id="global-products-count"', false);
        $response->assertSee('Produits dans le catalogue', false);
        $response->assertSee('id="global-products-count-inline"', false);
        $response->assertSee('Catalogue :', false);
    }

    public function test_admin_dashboard_shows_global_products_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedProducts(5);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('productsCount', 5);
        $response->assertSee('id="global-products-count"', false);
        $response->assertSee('Produits dans le catalogue', false);
    }

    public function test_admin_order_show_page_shows_global_products_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $worker = User::factory()->create(['role' => 'worker']);
        $this->seedProducts(2);

        $order = Order::create([
            'order_number' => 'ORD-ADMIN99',
            'worker_id' => $worker->id,
            'customer_name' => 'Client Test',
            'status' => 'received',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertViewHas('productsCount', 2);
        $response->assertSee('id="global-products-count"', false);
        $response->assertSee('Produits dans le catalogue', false);
    }

    private function seedProducts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'designation' => "Product {$i}",
                'qtu' => $i * 6,
            ]);
        }
    }
}
