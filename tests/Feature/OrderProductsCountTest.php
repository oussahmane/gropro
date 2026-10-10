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

    public function test_order_edit_page_shows_global_products_count(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        Product::create(['designation' => 'Product A', 'qtu' => 6]);
        Product::create(['designation' => 'Product B', 'qtu' => 12]);
        Product::create(['designation' => 'Product C', 'qtu' => 24]);

        $order = Order::create([
            'order_number' => 'ORD-TEST1234',
            'worker_id' => $worker->id,
            'customer_name' => '',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($worker)->get(route('worker.order.edit', $order));

        $response->assertOk();
        $response->assertViewHas('productsCount', 3);
        $response->assertSee('3 produit(s) dans le catalogue', false);
    }
}
