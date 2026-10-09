<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

class BoxUnitsLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_box_units_logic_is_stored_correctly()
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $product = Product::create(['designation' => 'Product A', 'qtu' => 12]);

        $this->actingAs($worker);

        // Case 1: Box = 3, Units = 12 (Method A: Full boxes)
        $response = $this->post(route('worker.order.store'), [
            'customer_name' => 'John Doe',
            'items' => [
                [
                    'product_id' => $product->id,
                    'box' => 3,
                    'units' => 12,
                ]
            ]
        ]);

        $response->assertSessionHas('success');
        
        $orderItem1 = OrderItem::latest('id')->first();
        $this->assertEquals(3, $orderItem1->box_quantity);
        $this->assertEquals(12, $orderItem1->units_quantity);
        $this->assertEquals(36, $orderItem1->box_quantity * $orderItem1->units_quantity); // Total individual units check

        // Case 2: Box = 1, Units = 2 (Method B: Individual quantity)
        $this->post(route('worker.order.store'), [
            'customer_name' => 'Jane Doe',
            'items' => [
                [
                    'product_id' => $product->id,
                    'box' => 1,
                    'units' => 2,
                ]
            ]
        ]);
        
        $orderItem2 = OrderItem::latest('id')->first();
        $this->assertEquals(1, $orderItem2->box_quantity);
        $this->assertEquals(2, $orderItem2->units_quantity);
        $this->assertEquals(2, $orderItem2->box_quantity * $orderItem2->units_quantity);

        // Case 3: Box = 1, Units = 30 (Method B: Large individual quantity)
        $this->post(route('worker.order.store'), [
            'customer_name' => 'Bob Smith',
            'items' => [
                [
                    'product_id' => $product->id,
                    'box' => 1,
                    'units' => 30,
                ]
            ]
        ]);
        
        $orderItem3 = OrderItem::latest('id')->first();
        $this->assertEquals(1, $orderItem3->box_quantity);
        $this->assertEquals(30, $orderItem3->units_quantity);
        $this->assertEquals(30, $orderItem3->box_quantity * $orderItem3->units_quantity);
    }
}
