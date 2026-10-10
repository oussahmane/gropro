<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxUnitsLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_box_units_logic_is_stored_correctly()
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $product = Product::create(['designation' => 'Product A', 'qtu' => 12]);

        $this->actingAs($worker);

        // Case 1: Box = 3, Units = 12 (Method A: Full boxes)
        $order1 = $this->createDraftOrder();
        $response = $this->post(route('worker.order.update', $order1), [
            'customer_name' => 'John Doe',
            'items' => [
                [
                    'product_id' => $product->id,
                    'designation' => $product->designation,
                    'qtu' => $product->qtu,
                    'box' => 3,
                    'units' => 12,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $orderItem1 = OrderItem::latest('id')->first();
        $this->assertEquals(3, $orderItem1->box_quantity);
        $this->assertEquals(12, $orderItem1->units_quantity);
        $this->assertEquals(36, $orderItem1->box_quantity * $orderItem1->units_quantity); // Total individual units check

        // Case 2: Box = 1, Units = 2 (Method B: Individual quantity)
        $order2 = $this->createDraftOrder();
        $this->post(route('worker.order.update', $order2), [
            'customer_name' => 'Jane Doe',
            'items' => [
                [
                    'product_id' => $product->id,
                    'designation' => $product->designation,
                    'qtu' => $product->qtu,
                    'box' => 1,
                    'units' => 2,
                ],
            ],
        ])->assertOk();

        $orderItem2 = OrderItem::latest('id')->first();
        $this->assertEquals(1, $orderItem2->box_quantity);
        $this->assertEquals(2, $orderItem2->units_quantity);
        $this->assertEquals(2, $orderItem2->box_quantity * $orderItem2->units_quantity);

        // Case 3: Box = 1, Units = 30 (Method B: Large individual quantity)
        $order3 = $this->createDraftOrder();
        $this->post(route('worker.order.update', $order3), [
            'customer_name' => 'Bob Smith',
            'items' => [
                [
                    'product_id' => $product->id,
                    'designation' => $product->designation,
                    'qtu' => $product->qtu,
                    'box' => 1,
                    'units' => 30,
                ],
            ],
        ])->assertOk();

        $orderItem3 = OrderItem::latest('id')->first();
        $this->assertEquals(1, $orderItem3->box_quantity);
        $this->assertEquals(30, $orderItem3->units_quantity);
        $this->assertEquals(30, $orderItem3->box_quantity * $orderItem3->units_quantity);
    }

    private function createDraftOrder(): Order
    {
        $this->get(route('worker.order.create'))->assertRedirect();

        return Order::latest('id')->first();
    }
}
