<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Address;
use App\Models\Order;
use PHPUnit\Framework\Attributes\Test;


class AddressApiTest extends TestCase
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
    public function a_customer_can_view_their_own_addresses()
    {
        $user = $this->createCustomerUser();
        $customer = $user->customer;
        $address1 = Address::factory()->for($customer)->create(['address_line1' => '123 Main St', 'is_default' => true]);
        $address2 = Address::factory()->for($customer)->create(['address_line1' => '456 Oak Ave', 'is_default' => false]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/addresses');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => ['id', 'address_line1', 'is_default']
                     ]
                 ])
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['address_line1' => '123 Main St'])
                 ->assertJsonFragment(['address_line1' => '456 Oak Ave']);

        // Ensure default address comes first due to orderBy('is_default', 'desc')
        $this->assertEquals($address1->id, $response->json('data.0.id'));
    }

    #[Test]
    public function an_admin_can_view_all_addresses()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer1 = $this->createCustomerUser();
        $customer2 = $this->createCustomerUser();
        $address1 = Address::factory()->for($customer1->customer)->create();
        $address2 = Address::factory()->for($customer2->customer)->create();

        $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/addresses');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => ['id', 'address_line1']
                     ]
                 ])
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['id' => $address1->id])
                 ->assertJsonFragment(['id' => $address2->id]);
    }

    #[Test]
    public function unauthenticated_user_cannot_view_addresses()
    {
        $response = $this->getJson('/api/addresses');
        $response->assertStatus(401); 
    }

    #[Test]
    public function a_customer_can_create_a_new_address()
    {
        $user = $this->createCustomerUser();
        $addressData = [
            'address_line1' => 'New Street',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'country' => 'Ghana',
            'is_default' => true,
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', $addressData);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'data' => ['id', 'address_line1']])
                 ->assertJson([
                     'message' => 'Address created successfully.',
                     'data' => [
                         'address_line1' => 'New Street',
                         'is_default' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('addresses', [
            'customer_id' => $user->customer->id,
            'address_line1' => 'New Street',
            'is_default' => true,
        ]);
    }

    #[Test]
    public function creating_an_address_makes_it_default_if_it_is_the_first_address()
    {
        $user = $this->createCustomerUser();
        // Ensure no addresses exist for this customer initially
        $user->customer->addresses()->delete();

        $addressData = [
            'address_line1' => 'First Address',
            'city' => 'Kumasi',
            'region' => 'Ashanti',
            'country' => 'Ghana',
            // is_default is not explicitly set to true, but should become default
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', $addressData);

        $response->assertStatus(201)
                 ->assertJson(['data' => ['is_default' => true]]);

        $this->assertDatabaseHas('addresses', [
            'customer_id' => $user->customer->id,
            'address_line1' => 'First Address',
            'is_default' => true,
        ]);
    }

    #[Test]
    public function creating_a_new_default_address_unsets_previous_default()
    {
        $user = $this->createCustomerUser();
        $customer = $user->customer;
        $oldDefault = Address::factory()->for($customer)->create(['is_default' => true]);
        $nonDefault = Address::factory()->for($customer)->create(['is_default' => false]);

        $newAddressData = [
            'address_line1' => 'New Default Address',
            'city' => 'Tamale',
            'region' => 'Northern',
            'country' => 'Ghana',
            'is_default' => true,
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', $newAddressData);

        $response->assertStatus(201)
                 ->assertJson(['data' => ['address_line1' => 'New Default Address', 'is_default' => true]]);

        $this->assertDatabaseHas('addresses', [
            'id' => $oldDefault->id,
            'is_default' => false, // Old default should be unset
        ]);
        $this->assertDatabaseHas('addresses', [
            'customer_id' => $user->customer->id,
            'address_line1' => 'New Default Address',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $nonDefault->id,
            'is_default' => false, // Non-default remains non-default
        ]);
    }

    #[Test]
    public function address_creation_fails_with_invalid_data()
    {
        $user = $this->createCustomerUser();
        $addressData = [
            'address_line1' => '', // Missing
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'country' => 'InvalidCountry', // Invalid
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', $addressData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['address_line1', 'country']);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_address()
    {
        $addressData = [
            'address_line1' => 'New Street',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'country' => 'Ghana',
        ];
        $response = $this->postJson('/api/addresses', $addressData);
        $response->assertStatus(403);
    }

    #[Test]
    public function a_customer_can_view_a_specific_address_they_own()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Address retrieved successfully.',
                     'data' => ['id' => $address->id],
                 ]);
    }

    #[Test]
    public function a_customer_cannot_view_another_customers_address()
    {
        $user1 = $this->createCustomerUser();
        $user2 = $this->createCustomerUser();
        $addressOfUser2 = Address::factory()->for($user2->customer)->create();

        $response = $this->actingAs($user1, 'sanctum')->getJson("/api/addresses/{$addressOfUser2->id}");

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized to view this address.']);
    }

    #[Test]
    public function an_admin_can_view_any_address()
    {
        $adminUser = $this->createCustomerUser('admin');
        $customer = $this->createCustomerUser();
        $address = Address::factory()->for($customer->customer)->create();

        $response = $this->actingAs($adminUser, 'sanctum')->getJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
                 ->assertJson(['data' => ['id' => $address->id]]);
    }

    #[Test]
    public function retrieving_non_existent_address_returns_404()
    {
        $user = $this->createCustomerUser();
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/addresses/9999');
        $response->assertStatus(404);
    }

    #[Test]
    public function a_customer_can_update_their_own_address()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create(['address_line1' => 'Old Address']);

        $updateData = ['address_line1' => 'Updated Address', 'is_default' => true];
        $response = $this->actingAs($user, 'sanctum')->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Address updated successfully.',
                     'data' => ['address_line1' => 'Updated Address', 'is_default' => true],
                 ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'address_line1' => 'Updated Address',
            'is_default' => true,
        ]);
    }

    #[Test]
    public function updating_an_address_to_default_unsets_previous_default()
    {
        $user = $this->createCustomerUser();
        $customer = $user->customer;
        $oldDefault = Address::factory()->for($customer)->create(['is_default' => true]);
        $addressToUpdate = Address::factory()->for($customer)->create(['is_default' => false]);

        $updateData = ['is_default' => true];
        $response = $this->actingAs($user, 'sanctum')->putJson("/api/addresses/{$addressToUpdate->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson(['data' => ['id' => $addressToUpdate->id, 'is_default' => true]]);

        $this->assertDatabaseHas('addresses', [
            'id' => $oldDefault->id,
            'is_default' => false, // Old default should be unset
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $addressToUpdate->id,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function address_update_fails_with_invalid_data()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();

        $updateData = [
            'city' => '', // Invalid
            'country' => 'InvalidCountry', // Invalid
        ];
        $response = $this->actingAs($user, 'sanctum')->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['city', 'country']);
    }

    #[Test]
    public function a_customer_cannot_update_another_customers_address()
    {
        $user1 = $this->createCustomerUser();
        $user2 = $this->createCustomerUser();
        $addressOfUser2 = Address::factory()->for($user2->customer)->create();

        $updateData = ['address_line1' => 'Attempted Update'];
        $response = $this->actingAs($user1, 'sanctum')->putJson("/api/addresses/{$addressOfUser2->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_update_address()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();

        $updateData = ['address_line1' => 'Attempted Update'];
        $response = $this->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function a_customer_can_delete_their_own_address()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Address deleted successfully.']);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    #[Test]
    public function deleting_last_address_does_not_fail()
    {
        $user = $this->createCustomerUser();
        $user->customer->addresses()->delete(); // Ensure no other addresses
        $address = Address::factory()->for($user->customer)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Address deleted successfully.']);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
        $this->assertDatabaseCount('addresses', 0);
    }

    #[Test]
    public function deleting_default_address_sets_new_default_if_others_exist()
    {
        $user = $this->createCustomerUser();
        $customer = $user->customer;
        $defaultAddress = Address::factory()->for($customer)->create(['is_default' => true]);
        $otherAddress = Address::factory()->for($customer)->create(['is_default' => false]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/addresses/{$defaultAddress->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('addresses', ['id' => $defaultAddress->id]);
        $this->assertDatabaseHas('addresses', [
            'id' => $otherAddress->id,
            'is_default' => true, // The other address should now be default
        ]);
    }

    #[Test]
    public function cannot_delete_address_linked_to_an_order()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();
        // Create an order using this address
        Order::factory()->create([
            'customer_id' => $user->customer->id,
            'delivery_address_id' => $address->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(409) // Conflict status code
                 ->assertJson(['message' => 'Cannot delete address as it is linked to existing orders.']);

        $this->assertDatabaseHas('addresses', ['id' => $address->id]); // Address should still exist
    }

    #[Test]
    public function a_customer_cannot_delete_another_customers_address()
    {
        $user1 = $this->createCustomerUser();
        $user2 = $this->createCustomerUser();
        $addressOfUser2 = Address::factory()->for($user2->customer)->create();

        $response = $this->actingAs($user1, 'sanctum')->deleteJson("/api/addresses/{$addressOfUser2->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_delete_address()
    {
        $user = $this->createCustomerUser();
        $address = Address::factory()->for($user->customer)->create();

        $response = $this->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(401); // Unauthenticated status code
    }
}
