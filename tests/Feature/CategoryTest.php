<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_categories(): void
    {
        Category::create(['name' => 'Shirt', 'slug' => 'shirt']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('0.slug', 'shirt')
            ->assertJsonPath('0.products_count', 0);
    }

    public function test_admin_can_add_category_with_generated_slug(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/categories', ['name' => 'Ethnic Wear'])
            ->assertCreated()
            ->assertJsonPath('slug', 'ethnic-wear');

        $this->postJson('/api/categories', ['name' => 'Ethnic Wear'])->assertStatus(422);
    }

    public function test_admin_can_remove_empty_category_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $empty = Category::create(['name' => 'Polo', 'slug' => 'polo']);
        $used = Category::create(['name' => 'Pant', 'slug' => 'pant']);
        Product::create(['name' => 'Chino', 'price' => 100, 'category_id' => $used->id]);

        $this->deleteJson("/api/categories/{$empty->id}")->assertOk();
        $this->deleteJson("/api/categories/{$used->id}")->assertStatus(422);

        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
        $this->assertDatabaseHas('categories', ['id' => $used->id]);
    }

    public function test_non_admin_cannot_change_categories(): void
    {
        $category = Category::create(['name' => 'Polo', 'slug' => 'polo']);

        $this->postJson('/api/categories', ['name' => 'X'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->postJson('/api/categories', ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->putJson("/api/categories/{$category->id}", ['is_active' => false])->assertForbidden();
    }

    public function test_paused_category_and_its_products_are_hidden_from_the_store_only(): void
    {
        $paused = Category::create(['name' => 'Polo', 'slug' => 'polo']);
        Category::create(['name' => 'Shirt', 'slug' => 'shirt']);
        $product = Product::create(['name' => 'Pique Polo', 'price' => 100, 'stock' => 5, 'category_id' => $paused->id]);
        Product::create(['name' => 'No category', 'price' => 50, 'stock' => 5]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/categories/{$paused->id}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);

        // Staff still see everything when they ask for it.
        $this->getJson('/api/categories?all=1')->assertJsonCount(2);
        $this->getJson('/api/products?all=1')->assertJsonCount(2);

        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->getJson('/api/categories?all=1')->assertJsonCount(1)->assertJsonPath('0.slug', 'shirt');
        $this->getJson('/api/products?all=1')->assertJsonCount(1)->assertJsonPath('0.name', 'No category');
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->postJson('/api/orders', [
            'name' => 'C', 'phone' => '1', 'address' => 'A', 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422);

        // Resuming brings it all back untouched.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/categories/{$paused->id}", ['is_active' => true])->assertOk();
        Sanctum::actingAs($customer);
        $this->getJson('/api/products')->assertJsonCount(2);
    }

    public function test_deleting_a_category_moves_detaches_or_deletes_its_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $target = Category::create(['name' => 'Shirt', 'slug' => 'shirt']);

        $a = Category::create(['name' => 'A', 'slug' => 'a']);
        $moved = Product::create(['name' => 'Moved', 'price' => 1, 'category_id' => $a->id]);
        $this->deleteJson("/api/categories/{$a->id}?products=move&move_to={$target->id}")->assertOk();
        $this->assertSame($target->id, $moved->fresh()->category_id);

        $b = Category::create(['name' => 'B', 'slug' => 'b']);
        $kept = Product::create(['name' => 'Kept', 'price' => 1, 'category_id' => $b->id]);
        $this->deleteJson("/api/categories/{$b->id}?products=detach")->assertOk();
        $this->assertNull($kept->fresh()->category_id);

        $c = Category::create(['name' => 'C', 'slug' => 'c']);
        $gone = Product::create(['name' => 'Gone', 'price' => 1, 'category_id' => $c->id]);
        $this->deleteJson("/api/categories/{$c->id}?products=move&move_to={$c->id}")->assertStatus(422);
        $this->deleteJson("/api/categories/{$c->id}?products=delete")->assertOk();
        $this->assertModelMissing($gone);
    }
}
