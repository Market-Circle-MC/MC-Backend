<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\DeliveryOption;
use App\Models\Order;
use PHPUnit\Framework\Attributes\Test;

class DeliveryOptionApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Helper to create a user with a customer profile.
     */
    protected function createCustomerUser(string $role = 'customer'): User
    {
        $user = User::factory()->create(['role' => $role]);
        Customer::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    #[Test]
    public function guests_can_view_only_active_delivery_options()
    {
        DeliveryOption::factory()->active()->create(['name' => 'Standard Active', 'cost' => 5.00]);
        DeliveryOption::factory()->inactive()->create(['name' => 'Inactive Option', 'cost' => 10.00]);

        $response = $this->getJson('/api/delivery-options');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => ['id', 'name', 'cost', 'is_active']
                     ]
                 ])
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Standard Active', 'is_active' => true])
                 ->assertJsonMissing(['name' => 'Inactive Option']);
    }

    #[Test]
    public function customers_can_view_only_active_delivery_options()
    {
        $user = $this->createCustomerUser();
        DeliveryOption::factory()->active()->create(['name' => 'Express Active', 'cost' => 15.00]);
        DeliveryOption::factory()->inactive()->create(['name' => 'Another Inactive', 'cost' => 20.00]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/delivery-options');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['name' => 'Express Active', 'is_active' => true])
                 ->assertJsonMissing(['name' => 'Another Inactive']);
    }

    #[Test]
    public function admins_can_view_all_delivery_options_including_inactive()
    {
        $adminUser = $this->createCustomerUser('admin');
        DeliveryOption::factory()->active()->create(['name' => 'Active Admin View', 'cost' => 25.00]);
        DeliveryOption::factory()->inactive()->create(['name' => 'Inactive Admin View', 'cost' => 30.00]);

        $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/delivery-options');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['name' => 'Active Admin View', 'is_active' => true])
                 ->assertJsonFragment(['name' => 'Inactive Admin View', 'is_active' => false]);
    }

    #[Test]
    public function admin_can_create_delivery_option()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryData = [
            'name' => 'New Express',
            'description' => 'Fast delivery service',
            'cost' => 25.50,
            'min_delivery_days' => 1,
            'max_delivery_days' => 2,
            'is_active' => true,
        ];

        $response = $this->actingAs($adminUser, 'sanctum')->postJson('/api/admin/delivery-options', $deliveryData);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'data' => ['id', 'name', 'cost']])
                 ->assertJson([
                     'message' => 'Delivery option created successfully.',
                     'data' => [
                         'name' => 'New Express',
                         'cost' => 25.50,
                         'is_active' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('delivery_options', ['name' => 'New Express', 'cost' => 25.50]);
    }

    #[Test]
    public function delivery_option_creation_fails_with_invalid_data()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryData = [
            'name' => '', // Missing
            'cost' => -10, // Invalid
            'max_delivery_days' => 1,
            'min_delivery_days' => 5, // max < min
        ];

        $response = $this->actingAs($adminUser, 'sanctum')->postJson('/api/admin/delivery-options', $deliveryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'cost', 'max_delivery_days']);
    }

    #[Test]
    public function delivery_option_creation_fails_with_non_unique_name()
    {
        $adminUser = $this->createCustomerUser('admin');
        DeliveryOption::factory()->create(['name' => 'Existing Delivery']);

        $deliveryData = [
            'name' => 'Existing Delivery', // Duplicate
            'cost' => 10.00,
            'is_active' => true,
        ];

        $response = $this->actingAs($adminUser, 'sanctum')->postJson('/api/admin/delivery-options', $deliveryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function non_admin_cannot_create_delivery_option()
    {
        $customerUser = $this->createCustomerUser();
        $deliveryData = ['name' => 'Attempted Option', 'cost' => 10.00];

        $response = $this->actingAs($customerUser, 'sanctum')->postJson('/api/admin/delivery-options', $deliveryData);
        $response->assertStatus(403); // Forbidden

        $response = $this->postJson('/api/admin/delivery-options', $deliveryData);
        $response->assertStatus(403); // Unauthorized
    }

    #[Test]
    public function guest_cannot_view_inactive_delivery_option_by_id()
    {
        $inactiveOption = DeliveryOption::factory()->inactive()->create(['name' => 'Hidden Option']);

        $response = $this->getJson("/api/delivery-options/{$inactiveOption->id}");
        $response->assertStatus(404) // Not Found or Inactive
                 ->assertJson(['message' => 'Delivery option not found or is inactive.']);
    }

    #[Test]
    public function customer_cannot_view_inactive_delivery_option_by_id()
    {
        $user = $this->createCustomerUser();
        $inactiveOption = DeliveryOption::factory()->inactive()->create(['name' => 'Hidden Option']);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/delivery-options/{$inactiveOption->id}");
        $response->assertStatus(404) // Not Found or Inactive
                 ->assertJson(['message' => 'Delivery option not found or is inactive.']);
    }

    #[Test]
    public function admin_can_view_any_delivery_option_by_id()
    {
        $adminUser = $this->createCustomerUser('admin');
        $activeOption = DeliveryOption::factory()->active()->create(['name' => 'Visible Option']);
        $inactiveOption = DeliveryOption::factory()->inactive()->create(['name' => 'Hidden Option']);

        $responseActive = $this->actingAs($adminUser, 'sanctum')->getJson("/api/delivery-options/{$activeOption->id}");
        $responseActive->assertStatus(200)
                       ->assertJsonFragment(['name' => 'Visible Option']);

        $responseInactive = $this->actingAs($adminUser, 'sanctum')->getJson("/api/delivery-options/{$inactiveOption->id}");
        $responseInactive->assertStatus(200)
                         ->assertJsonFragment(['name' => 'Hidden Option']);
    }

    #[Test]
    public function retrieving_non_existent_delivery_option_returns_404()
    {
        $response = $this->getJson('/api/delivery-options/9999');
        $response->assertStatus(404);

        $adminUser = $this->createCustomerUser('admin');
        $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/admin/delivery-options/9999');
        $response->assertStatus(404);
    }

    #[Test]
    public function admin_can_update_delivery_option()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryOption = DeliveryOption::factory()->create(['name' => 'Old Name', 'cost' => 10.00, 'is_active' => true]);

        $updateData = [
            'name' => 'Updated Name',
            'cost' => 12.50,
            'is_active' => false,
        ];
        $response = $this->actingAs($adminUser, 'sanctum')->putJson("/api/admin/delivery-options/{$deliveryOption->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Delivery option updated successfully.',
                     'data' => [
                         'name' => 'Updated Name',
                         'cost' => 12.50,
                         'is_active' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('delivery_options', [
            'id' => $deliveryOption->id,
            'name' => 'Updated Name',
            'cost' => 12.50,
            'is_active' => false,
        ]);
    }

    #[Test]
    public function admin_can_partially_update_delivery_option()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryOption = DeliveryOption::factory()->create(['name' => 'Partial Update', 'cost' => 10.00, 'is_active' => true]);

        $updateData = ['cost' => 15.00]; // Only update cost
        $response = $this->actingAs($adminUser, 'sanctum')->patchJson("/api/admin/delivery-options/{$deliveryOption->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Delivery option updated successfully.',
                     'data' => [
                         'name' => 'Partial Update', // Should remain unchanged
                         'cost' => 15.00,
                         'is_active' => true, // Should remain unchanged
                     ]
                 ]);

        $this->assertDatabaseHas('delivery_options', [
            'id' => $deliveryOption->id,
            'name' => 'Partial Update',
            'cost' => 15.00,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function delivery_option_update_fails_with_invalid_data()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryOption = DeliveryOption::factory()->create();

        $updateData = [
            'name' => '', // Invalid
            'cost' => 'abc', // Invalid
        ];
        $response = $this->actingAs($adminUser, 'sanctum')->putJson("/api/admin/delivery-options/{$deliveryOption->id}", $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'cost']);
    }

    #[Test]
    public function delivery_option_update_fails_with_non_unique_name()
    {
        $adminUser = $this->createCustomerUser('admin');
        DeliveryOption::factory()->create(['name' => 'Existing Name']);
        $deliveryOptionToUpdate = DeliveryOption::factory()->create(['name' => 'Unique Name']);

        $updateData = ['name' => 'Existing Name']; // Duplicate
        $response = $this->actingAs($adminUser, 'sanctum')->putJson("/api/admin/delivery-options/{$deliveryOptionToUpdate->id}", $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function non_admin_cannot_update_delivery_option()
    {
        $customerUser = $this->createCustomerUser();
        $deliveryOption = DeliveryOption::factory()->create();

        $updateData = ['cost' => 100.00];
        $response = $this->actingAs($customerUser, 'sanctum')->putJson("/api/admin/delivery-options/{$deliveryOption->id}", $updateData);
        $response->assertStatus(403);

        $response = $this->putJson("/api/admin/delivery-options/{$deliveryOption->id}", $updateData);
        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_delete_delivery_option()
    {
        $adminUser = $this->createCustomerUser('admin');
        $deliveryOption = DeliveryOption::factory()->create();

        $response = $this->actingAs($adminUser, 'sanctum')->deleteJson("/api/admin/delivery-options/{$deliveryOption->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Delivery option deleted successfully.']);

        $this->assertDatabaseMissing('delivery_options', ['id' => $deliveryOption->id]);
    }

    #[Test]
    public function cannot_delete_delivery_option_linked_to_an_order()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer = $this->createCustomerUser();
        $deliveryOption = DeliveryOption::factory()->create();
        // Create an order using this delivery option
        Order::factory()->create([
            'customer_id' => $customer->customer->id,
            'delivery_option_id' => $deliveryOption->id,
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')->deleteJson("/api/admin/delivery-options/{$deliveryOption->id}");

        $response->assertStatus(409) // Conflict status code
                 ->assertJson(['message' => 'Cannot delete delivery option as it is linked to existing orders.']);

        $this->assertDatabaseHas('delivery_options', ['id' => $deliveryOption->id]); // Option should still exist
    }

    #[Test]
    public function non_admin_cannot_delete_delivery_option()
    {
        $customerUser = $this->createCustomerUser();
        $deliveryOption = DeliveryOption::factory()->create();

        $response = $this->actingAs($customerUser, 'sanctum')->deleteJson("/api/admin/delivery-options/{$deliveryOption->id}");
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/admin/delivery-options/{$deliveryOption->id}");
        $response->assertStatus(403);
    }
}
