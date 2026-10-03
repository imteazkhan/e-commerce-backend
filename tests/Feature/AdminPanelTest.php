<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, Product $product, int $qty, string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'name' => $customer->name, 'phone' => '01700000000',
            'address' => 'Dhaka', 'payment_method' => 'cod', 'total' => $product->price * $qty, 'status' => $status,
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'qty' => $qty, 'price' => $product->price]);

        return $order;
    }

    private function product(int $stock = 10): Product
    {
        $category = Category::firstOrCreate(['slug' => 'shirt'], ['name' => 'Shirt']);

        return Product::create(['name' => 'Oxford', 'price' => 100, 'stock' => $stock, 'category_id' => $category->id]);
    }

    public function test_dashboard_reports_revenue_without_cancelled_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product(3);
        $this->order($customer, $product, 2);
        $this->order($customer, $product, 5, 'cancelled');

        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $this->getJson('/api/dashboard?days=7')
            ->assertOk()
            ->assertJsonPath('revenue', 200)
            ->assertJsonPath('orders', 2)
            ->assertJsonPath('low_stock_count', 1)
            ->assertJsonPath('top_products.0.qty', 2)
            ->assertJsonCount(7, 'sales');
    }

    public function test_orders_can_be_filtered_and_counted(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Rahim']);
        $product = $this->product();
        $this->order($customer, $product, 1);
        $this->order($customer, $product, 1, 'delivered');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/orders?status=delivered&search=rahim')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('counts.delivered', 1);
    }

    public function test_cancelling_restores_stock_and_reopening_takes_it_again(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product(4);
        $order = $this->order($customer, $product, 3);

        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'cancelled'])->assertOk();
        $this->assertSame(7, $product->fresh()->stock);

        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'processing'])->assertOk();
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_only_admin_can_change_payment_status(): void
    {
        $order = $this->order(User::factory()->create(['role' => 'customer']), $this->product(), 1);

        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->putJson("/api/admin/orders/{$order->id}", ['payment_status' => 'paid'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/admin/orders/{$order->id}", ['payment_status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid');
    }

    public function test_admin_can_create_staff_and_see_customer_stats(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->order($customer, $this->product(), 2);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/admin/users', [
            'name' => 'New Manager', 'email' => 'm@example.com', 'password' => 'secret12', 'role' => 'manager',
        ])->assertCreated()->assertJsonPath('role', 'manager');

        $this->getJson("/api/admin/users/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('orders.0.items_count', 1);
    }

    public function test_customers_cannot_reach_staff_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/dashboard')->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->getJson('/api/admin/users')->assertForbidden();
    }
}
