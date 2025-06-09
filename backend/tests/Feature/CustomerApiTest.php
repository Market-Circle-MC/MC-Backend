<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;

class CustomerApiTest extends TestCase
{
    // This trait will reset the database before each test method runs.
    // This ensures that each test starts with a clean slate, preventing data from one test
    // affecting another. Essential for reliable testing.
    use RefreshDatabase;

    /**
     * Helper method to create and authenticate a user for tests.
     * Most of our API endpoints will require an authenticated user.
     *
     * @return \App\Models\User The created and authenticated user instance.
     */
    protected function createAuthenticatedUser(): User
    {
        // Create a new user using the User factory. Factories provide dummy data.
        $user = User::factory()->create();

        // Simulate logging in as this user for the current test.
        // 'sanctum' is the API authentication guard typically used with Laravel Sanctum.
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESSFUL CUSTOMER PROFILE CREATION TESTS (HTTP Status: 201 Created)
    |--------------------------------------------------------------------------
    | These tests verify that valid data can be successfully used to create
    | customer profiles of different types, adhering to conditional rules.
    */

    /**
     * Test that a valid 'family' type customer profile can be created successfully.
     * 'family' customers do not require 'business_name' or 'tax_id'.
     */
    public function test_family_customer_profile_can_be_created_successfully()
    {
        // Create and authenticate a user who will own this customer profile.
        $user = $this->createAuthenticatedUser();

        // Define the data for the new customer profile.
        // Note that 'business_name' and 'tax_id' are intentionally omitted,
        // as they are nullable and not required for 'family' customer_type.
        $customerData = [
            'user_id' => $user->id,
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'GA-123-4567', // Example valid Ghana Post GPS address
            'digital_address' => 'East Legon, Near Boundary Road', // Example digital address
            'contact_person_name' => 'Adwoa Mensah',
            'contact_person_phone' => '+233241234567', // Example valid Ghanaian phone number
        ];

        // Send a POST request to the '/api/customers' endpoint with JSON data.
        $response = $this->postJson('/api/customers', $customerData);

        // Assert that the HTTP response status code is 201 (Created).
        $response->assertStatus(201)
            // Assert that the JSON response contains the success message and specific customer data.
            ->assertJson([
                'message' => 'Customer profile created successfully',
                'customer' => [
                    'user_id' => $user->id,
                    'customer_type' => 'family',
                    'ghanapost_gps_address' => 'GA-123-4567',
                    'digital_address' => 'East Legon, Near Boundary Road',
                    'contact_person_name' => 'Adwoa Mensah',
                    'contact_person_phone' => '+233241234567',
                    'id' => $customerData['user_id'],
                ]
            ]);

        // Use assertJsonStructure to verify the presence of dynamic fields like timestamps
        $response->assertJsonStructure([
        'message',
        'customer' => [
            'id',
            'user_id',
            'customer_type',
            'ghanapost_gps_address',
            'digital_address',
            'contact_person_name',
            'contact_person_phone',
            'created_at',
            'updated_at',
        ]
    ]);

        // Assert that the newly created customer record exists in the 'customers' database table.
        // We check for the key fields to confirm the record was saved correctly.
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'customer_type' => 'family',
            'business_name' => null,
            'tax_id' => null,
        ]);
    }

    /**
     * Test that a 'restaurant' type customer profile can be created successfully
     * with a valid GRA TIN (Ghana Revenue Authority Tax Identification Number).
     */
    public function test_restaurant_customer_profile_can_be_created_with_valid_gra_tin()
    {
        // Create and authenticate a user.
        $user = $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => $user->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Best Restaurant Ever Ltd.', // Required for 'restaurant' type
            'ghanapost_gps_address' => 'CR-543-2109',
            'digital_address' => 'Ridge, Accra',
            'tax_id' => 'C0098765432', // Valid GRA TIN format (C00 + 8 digits)
            'contact_person_name' => 'Kojo Osei',
            'contact_person_phone' => '+233551234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        // Expect 201 Created status and verify key data including the tax_id.
        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Customer profile created successfully',
                'customer' => [
                    'user_id' => $user->id,
                    'customer_type' => 'restaurant',
                    'tax_id' => 'C0098765432',
                    'id' => $customerData['user_id'],
                ]
            ]);
            // Verify the JSON structure includes dynamic fields like timestamps.
            $response->assertJsonStructure([
            'message',
            'customer' => [
                'id',
                'user_id',
                'customer_type',
                'business_name',
                'tax_id',
                'ghanapost_gps_address',
                'digital_address',
                'contact_person_name',
                'contact_person_phone',
                'created_at',
                'updated_at',
            ]
        ]);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'tax_id' => 'C0098765432',
        ]);
    }

    /**
     * Test that a 'restaurant' type customer profile can be created successfully
     * with a valid GhanaCard PIN, which also serves as a TIN.
     */
    public function test_restaurant_customer_profile_can_be_created_with_valid_ghanacard_pin()
    {
        // Create and authenticate a user.
        $user = $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => $user->id,
            'customer_type' => 'restaurant',
            'business_name' => 'The Eatery Place',
            'ghanapost_gps_address' => 'AS-123-4567',
            'digital_address' => 'Kumasi City Centre',
            'tax_id' => 'GHA-12345678-A', // Valid GhanaCard PIN (3-letters-8-digits-1-checksum)
            'contact_person_name' => 'Abena Serwaa',
            'contact_person_phone' => '+233209876543',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Customer profile created successfully',
                'customer' => [
                    'user_id' => $user->id,
                    'customer_type' => 'restaurant',
                    'tax_id' => 'GHA-12345678-A',
                    'id' => $customerData['user_id'],
                ]
            ]);
        // Verify the JSON structure includes dynamic fields like timestamps.
        $response->assertJsonStructure([
            'message',
            'customer' => [
                'id',
                'user_id',
                'customer_type',
                'business_name', // Should be present but null
                'tax_id',        // Should be present but null
                'ghanapost_gps_address',
                'digital_address',
                'contact_person_name',
                'contact_person_phone',
                'created_at',
                'updated_at',
            ]
        ]);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'tax_id' => 'GHA-12345678-A',
        ]);

        // Test with 9-digit GhanaCard PIN
        $anotherUser = User::factory()->create();
        $this->actingAs($anotherUser, 'sanctum'); // Authenticate a new user

        $customerData2 = [
            'user_id' => $anotherUser->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Coastal Cuisine',
            'ghanapost_gps_address' => 'CP-000-1111',
            'digital_address' => 'Cape Coast Beach',
            'tax_id' => 'GHA-987654321-B', // Valid GhanaCard PIN (3-letters-9-digits-1-checksum)
            'contact_person_name' => 'Kweku Addo',
            'contact_person_phone' => '+233271122334',
        ];

        $response2 = $this->postJson('/api/customers', $customerData2);

        $response2->assertStatus(201)
            ->assertJson([
                'message' => 'Customer profile created successfully',
                'customer' => [
                    'tax_id' => 'GHA-987654321-B',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'user_id' => $anotherUser->id,
            'tax_id' => 'GHA-987654321-B',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION FAILURE TESTS (HTTP Status: 422 Unprocessable Entity)
    |--------------------------------------------------------------------------
    | These tests verify that invalid data triggers appropriate validation errors.
    */

    /**
     * Test that customer creation fails if 'user_id' is missing from the request.
     */
    public function test_customer_creation_fails_if_user_id_is_missing()
    {
        // Authenticate a user (though the specific user_id won't be used in the data,
        // we still need to be authenticated if routes are protected).
        $this->createAuthenticatedUser();

        $customerData = [
            // 'user_id' is intentionally omitted here
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Missing User ID',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        // Assert that the response status is 422 (Unprocessable Entity) for validation errors.
        $response->assertStatus(422)
            // Assert that the 'user_id' field specifically has a validation error.
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test that customer creation fails if the provided 'user_id' does not exist in the 'users' table.
     */
    public function test_customer_creation_fails_if_user_id_does_not_exist()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => 99999, // A non-existent user ID
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Non Existent User',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
        // Optionally, assert the specific error message from your messages() method in FormRequest
        $response->assertJsonFragment([
            'user_id' => ['The selected user does not exist.']
        ]);
    }

    /**
     * Test that customer creation fails if a profile already exists for the given 'user_id'.
     * This checks the 'unique:customers,user_id' rule.
     */
    public function test_customer_creation_fails_if_profile_already_exists_for_user_id()
    {
        // Create and authenticate a user.
        $user = $this->createAuthenticatedUser();

        // Create an existing customer profile for this user.
        Customer::factory()->create([
            'user_id' => $user->id,
            'customer_type' => 'individual_bulk', // Example type
            'ghanapost_gps_address' => 'GR-000-0000',
            'contact_person_name' => 'Existing Customer',
            'contact_person_phone' => '+233200000000'
        ]);

        // Attempt to create another customer profile for the SAME user.
        $customerData = [
            'user_id' => $user->id, // This user_id already has a customer profile
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Duplicate User Attempt',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
        // Assert the custom unique error message.
        $response->assertJsonFragment([
            'user_id' => ['A customer profile already exists for the selected user.']
        ]);
    }

    /**
     * Test that customer creation fails if 'customer_type' is missing.
     */
    public function test_customer_creation_fails_if_customer_type_is_missing()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            // 'customer_type' is missing
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Missing Type',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_type']);
    }

    /**
     * Test that customer creation fails if 'customer_type' is an invalid value.
     */
    public function test_customer_creation_fails_if_customer_type_is_invalid()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'unsupported_type', // Invalid type
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Invalid Type',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_type']);
        $response->assertJsonFragment([
            'customer_type' => ['The customer type must be one of: restaurant, family, or individual_bulk.']
        ]);
    }

    /**
     * Test that a 'restaurant' type customer creation fails if 'business_name' is missing.
     */
    public function test_restaurant_customer_fails_if_business_name_is_missing()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'restaurant',
            // 'business_name' is missing
            'ghanapost_gps_address' => 'GA-123-4567',
            'tax_id' => 'C0012345678', // Tax ID is provided to isolate business_name error
            'contact_person_name' => 'No Biz Name',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_name']);
    }

    /**
     * Test that a 'restaurant' type customer creation fails if 'tax_id' is missing.
     */
    public function test_restaurant_customer_fails_if_tax_id_is_missing()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Biz Name Present',
            'ghanapost_gps_address' => 'GA-123-4567',
            // 'tax_id' is missing
            'contact_person_name' => 'No Tax ID',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
    }

    /**
     * Test that 'tax_id' validation fails for an invalid GRA TIN format.
     */
    public function test_tax_id_validation_fails_for_invalid_gra_tin_format()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Invalid TIN Test',
            'ghanapost_gps_address' => 'GT-000-1111',
            'tax_id' => 'X0012345678', // Invalid prefix (should be P, C, G, Q, V)
            'contact_person_name' => 'Invalid TIN',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
        $response->assertJsonFragment([
            'tax_id' => ['The tax id format is invalid. It must be either a valid GRA TIN (e.g., C0012345678) or a GhanaCard PIN (e.g., GHA-12345678-X or GHA-123456789-Y).']
        ]);

        // Another invalid GRA TIN (wrong length)
        $anotherUser = User::factory()->create();
        $this->actingAs($anotherUser, 'sanctum');
        $customerData['user_id'] = $anotherUser->id;
        $customerData['tax_id'] = 'C00123'; // Too short

        $response = $this->postJson('/api/customers', $customerData);
        $response->assertStatus(422)->assertJsonValidationErrors(['tax_id']);
    }

    /**
     * Test that 'tax_id' validation fails for an invalid GhanaCard PIN format.
     */
    public function test_tax_id_validation_fails_for_invalid_ghanacard_pin_format()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Invalid GhanaCard Test',
            'ghanapost_gps_address' => 'GR-111-2222',
            'tax_id' => 'GHA12345678-X', // Missing hyphen after country code
            'contact_person_name' => 'Invalid GhanaCard',
            'contact_person_phone' => '+233241234567',
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
        $response->assertJsonFragment([
            'tax_id' => ['The tax id format is invalid. It must be either a valid GRA TIN (e.g., C0012345678) or a GhanaCard PIN (e.g., GHA-12345678-X or GHA-123456789-Y).']
        ]);

        // Another invalid GhanaCard PIN (wrong checksum)
        $anotherUser = User::factory()->create();
        $this->actingAs($anotherUser, 'sanctum');
        $customerData['user_id'] = $anotherUser->id;
        $customerData['tax_id'] = 'GHA-12345678-!'; // Invalid checksum character

        $response = $this->postJson('/api/customers', $customerData);
        $response->assertStatus(422)->assertJsonValidationErrors(['tax_id']);
    }

    /**
     * Test that 'contact_person_phone' validation fails for an invalid Ghanaian phone number format.
     */
    public function test_contact_person_phone_fails_for_invalid_ghanaian_format()
    {
        $this->createAuthenticatedUser();

        $customerData = [
            'user_id' => User::factory()->create()->id,
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'TT-000-9999',
            'contact_person_name' => 'Wrong Phone',
            'contact_person_phone' => '123-456-7890', // Invalid format
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_person_phone']);
        // Laravel's default regex error message is usually "The field format is invalid."
        // If you want a custom message, define it in your StoreCustomerRequest's messages() method.
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZATION FAILURE TESTS (HTTP Status: 401 Unauthorized / 403 Forbidden)
    |--------------------------------------------------------------------------
    | These tests verify that unauthenticated users cannot access the endpoint.
    */

    /**
     * Test that an unauthenticated user cannot create a customer profile.
     * This checks the 'auth:sanctum' middleware on the route.
     */
    public function test_unauthenticated_user_cannot_create_customer_profile()
    {
        // Do NOT call createAuthenticatedUser() here. We want to test without authentication.
        $customerData = [
            'user_id' => User::factory()->create()->id, // Still need a valid user ID for the request data itself
            'customer_type' => 'family',
            'ghanapost_gps_address' => 'GA-123-4567',
            'contact_person_name' => 'Unauthenticated Test',
            'contact_person_phone' => '+233241234567',
        ];

        // Send the request without any authentication headers.
        $response = $this->postJson('/api/customers', $customerData);

        // Assert that the HTTP response status code is 401 (Unauthorized).
        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PROFILE UPDATE TESTS (HTTP Status: 200 OK / 422 Unprocessable Entity / 403 Forbidden)
    |--------------------------------------------------------------------------
    | These tests verify the update functionality, including authorization and validation.
    */

    /**
     * Test that an authenticated user can successfully perform a full update of their customer profile.
     */
    public function test_authenticated_user_can_fully_update_their_customer_profile()
    {
        // 1. Arrange: Create a user and a customer profile for that user.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create(['user_id' => $user->id]); // Create a family customer

        // 2. Act: Define the new data for the update (changing type from family to restaurant)
        $updatedData = [
            'customer_type' => 'restaurant',
            'business_name' => 'Updated Restaurant Name',
            'ghanapost_gps_address' => 'GT-999-8888',
            'digital_address' => 'Updated Digital Address',
            'tax_id' => 'P0011223344', // Valid GRA TIN
            'contact_person_name' => 'Updated Contact Person',
            'contact_person_phone' => '+233501234567',
        ];

        // Send a PUT request to the update endpoint.
        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // 3. Assert:
        // Expect a 200 OK status.
        $response->assertStatus(200)
            // Assert that the JSON response contains the success message and updated data.
            ->assertJson([
                'message' => 'Customer profile updated successfully',
                'customer' => [
                    'id' => $customer->id, // ID should remain the same
                    'user_id' => $user->id, // User ID should remain the same
                    'customer_type' => 'restaurant',
                    'business_name' => 'Updated Restaurant Name',
                    'ghanapost_gps_address' => 'GT-999-8888',
                    'digital_address' => 'Updated Digital Address',
                    'tax_id' => 'P0011223344',
                    'contact_person_name' => 'Updated Contact Person',
                    'contact_person_phone' => '+233501234567',
                ]
            ])
            ->assertJsonStructure([ // Verify structure including timestamps
                'message',
                'customer' => [
                    'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                    'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                    'contact_person_phone', 'created_at', 'updated_at',
                ]
            ]);

        // Assert that the database record has been updated with the new data.
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => $user->id,
            'customer_type' => 'restaurant',
            'business_name' => 'Updated Restaurant Name',
            'ghanapost_gps_address' => 'GT-999-8888',
            'digital_address' => 'Updated Digital Address',
            'tax_id' => 'P0011223344',
            'contact_person_name' => 'Updated Contact Person',
            'contact_person_phone' => '+233501234567',
        ]);
    }

    /**
     * Test that an authenticated user can successfully perform a partial update of their customer profile.
     */
    public function test_authenticated_user_can_partially_update_their_customer_profile()
    {
        // Arrange: Create a user and an existing family customer profile.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create([
            'user_id' => $user->id,
            'ghanapost_gps_address' => 'AA-111-2222',
            'contact_person_name' => 'Original Name',
        ]);

        // Act: Only update the contact_person_name and digital_address.
        $partialUpdateData = [
            'contact_person_name' => 'New Partial Name',
            'digital_address' => 'New Digital Address',
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $partialUpdateData);

        // Assert:
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer profile updated successfully',
                'customer' => [
                    'id' => $customer->id,
                    'user_id' => $user->id,
                    'contact_person_name' => 'New Partial Name',
                    'digital_address' => 'New Digital Address',
                    // The other fields should remain their original values
                    'customer_type' => $customer->customer_type, // Should be 'family'
                    'ghanapost_gps_address' => 'AA-111-2222',
                    'business_name' => null,
                    'tax_id' => null,
                ]
            ]);

        // Verify the database reflects the partial update, with other fields unchanged.
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'contact_person_name' => 'New Partial Name',
            'digital_address' => 'New Digital Address',
            'customer_type' => $customer->customer_type,
            'ghanapost_gps_address' => 'AA-111-2222',
        ]);
    }

    /**
     * Test that a 'restaurant' customer can be updated to 'family' type,
     * and 'business_name' and 'tax_id' can be set to null.
     */
    public function test_restaurant_customer_can_be_converted_to_family_type()
    {
        // Arrange: Create a user and an existing restaurant customer profile.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->restaurant()->create([
            'user_id' => $user->id,
            'business_name' => 'Original Restaurant',
            'tax_id' => 'C0012345678',
        ]);

        // Act: Update to 'family' type and explicitly set business_name and tax_id to null.
        $updatedData = [
            'customer_type' => 'family',
            'business_name' => null,
            'tax_id' => null,
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert:
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer profile updated successfully',
                'customer' => [
                    'id' => $customer->id,
                    'customer_type' => 'family',
                    'business_name' => null,
                    'tax_id' => null,
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'customer_type' => 'family',
            'business_name' => null,
            'tax_id' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE VALIDATION FAILURE TESTS (HTTP Status: 422 Unprocessable Entity)
    |--------------------------------------------------------------------------
    */

    /**
     * Test that updating 'customer_type' to 'restaurant' fails if 'business_name' is then missing.
     */
    public function test_update_to_restaurant_fails_if_business_name_is_missing()
    {
        // Arrange: Start with a family customer.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create(['user_id' => $user->id]);

        // Act: Attempt to change to 'restaurant' type without providing business_name.
        $updatedData = [
            'customer_type' => 'restaurant',
            // 'business_name' is missing
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect validation error.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_name']);
    }

    /**
     * Test that updating 'customer_type' to 'restaurant' fails if 'tax_id' is then missing.
     */
    public function test_update_to_restaurant_fails_if_tax_id_is_missing()
    {
        // Arrange: Start with a family customer.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create(['user_id' => $user->id]);

        // Act: Attempt to change to 'restaurant' type without providing tax_id.
        $updatedData = [
            'customer_type' => 'restaurant',
            'business_name' => 'New Business Name', // Provide business_name to isolate tax_id error
            // 'tax_id' is missing
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect validation error.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
    }

    /**
     * Test that updating a customer with an invalid 'tax_id' format fails.
     */
    public function test_update_fails_with_invalid_tax_id_format()
    {
        // Arrange: Create a restaurant customer.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->restaurant()->create(['user_id' => $user->id]);

        // Act: Attempt to update with an invalid tax_id.
        $updatedData = [
            'tax_id' => 'INVALID_FORMAT',
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect validation error.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
        $response->assertJsonFragment([
            'tax_id' => ['The tax id format is invalid. It must be either a valid GRA TIN (e.g., C0012345678) or a GhanaCard PIN (e.g., GHA-12345678-X or GHA-123456789-Y).']
        ]);
    }

    /**
     * Test that updating a customer with an invalid 'contact_person_phone' format fails.
     */
    public function test_update_fails_with_invalid_contact_person_phone_format()
    {
        // Arrange: Create a family customer.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create(['user_id' => $user->id]);

        // Act: Attempt to update with an invalid phone number.
        $updatedData = [
            'contact_person_phone' => '123-INVALID-PHONE',
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect validation error.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_person_phone']);
    }

    /**
     * Test that an attempt to change the user_id of a customer profile fails.
     */
    public function test_cannot_change_user_id_of_customer_profile()
    {
        // Arrange: Create two users and a customer profile for the first user.
        $user1 = $this->createAuthenticatedUser();
        $user2 = User::factory()->create(); // Another user
        $customer = Customer::factory()->family()->create(['user_id' => $user1->id]);

        // Act: Attempt to change the user_id of the customer profile to user2's ID.
        $updatedData = [
            'user_id' => $user2->id,
            'contact_person_name' => 'Attempted User Change', // Other fields to make it a valid request otherwise
        ];

        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect validation error for user_id.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
        $response->assertJsonFragment([
            'user_id' => ['The user ID cannot be changed for an existing customer profile.']
        ]);

        // Also assert that the user_id in the database has NOT changed.
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => $user1->id, // Still the original user_id
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE AUTHORIZATION FAILURE TESTS (HTTP Status: 401 Unauthorized / 403 Forbidden)
    |--------------------------------------------------------------------------
    */

    /**
     * Test that an unauthenticated user cannot update any customer profile.
     */
    public function test_unauthenticated_user_cannot_update_customer_profile()
    {
        // Arrange: Create a customer profile (it doesn't matter who owns it for this test).
        $customer = Customer::factory()->create();

        // Act: Attempt to update without authentication.
        $updatedData = ['contact_person_name' => 'Unauthorized Update'];
        $response = $this->putJson("/api/customers/{$customer->id}", $updatedData);

        // Assert: Expect 401 Unauthorized.
        $response->assertStatus(401);
    }

    /**
     * Test that an authenticated user cannot update another user's customer profile.
     */
    public function test_authenticated_user_cannot_update_another_users_customer_profile()
    {
        // Arrange: Create User A and their customer profile.
        $userA = $this->createAuthenticatedUser(); // This is the user who will be authenticated.
        $customerA = Customer::factory()->family()->create(['user_id' => $userA->id]);

        // Create User B and their customer profile.
        $userB = User::factory()->create(); // This user is NOT authenticated for this request.
        $customerB = Customer::factory()->family()->create(['user_id' => $userB->id]);

        // Act: Authenticated User A tries to update Customer B's profile.
        $updatedData = ['contact_person_name' => 'User A trying to update User B'];
        $response = $this->putJson("/api/customers/{$customerB->id}", $updatedData);

        // Assert: Expect 403 Forbidden because User A doesn't own Customer B's profile.
        $response->assertStatus(403);

        // Verify that Customer B's profile in the database remains unchanged.
        $this->assertDatabaseHas('customers', [
            'id' => $customerB->id,
            'contact_person_name' => $customerB->contact_person_name, // Should be original name
        ]);
    }

    /**
     * Test that an authenticated user receives 404 if they try to update a non-existent customer profile.
     */
    public function test_authenticated_user_gets_404_for_non_existent_customer_profile()
    {
        // Arrange: Authenticate a user.
        $this->createAuthenticatedUser();

        // Act: Try to update a customer with a non-existent ID.
        $nonExistentId = 99999;
        $updatedData = ['contact_person_name' => 'Non Existent'];
        $response = $this->putJson("/api/customers/{$nonExistentId}", $updatedData);

        // Assert: Expect 404 Not Found (due to Route Model Binding).
        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PROFILE RETRIEVAL TESTS (HTTP Status: 200 OK / 401 Unauthorized / 403 Forbidden / 404 Not Found)
    |--------------------------------------------------------------------------
    */

    /**
     * Test that an authenticated user can retrieve their own customer profile.
     */
    public function test_authenticated_user_can_retrieve_their_own_customer_profile()
    {
        // 1. Arrange: Create a user and their customer profile.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create([
            'user_id' => $user->id,
            'ghanapost_gps_address' => 'AB-111-2222', // Specific data to assert
            'contact_person_name' => 'John Doe',
        ]);

        // 2. Act: Send a GET request to retrieve this customer profile.
        $response = $this->getJson("/api/customers/{$customer->id}");

        // 3. Assert:
        // Expect a 200 OK status.
        $response->assertStatus(200)
            // Assert that the JSON response contains the success message and specific customer data.
            ->assertJson([
                'message' => 'Customer profile retrieved successfully',
                'customer' => [
                    'id' => $customer->id,
                    'user_id' => $user->id,
                    'customer_type' => 'family',
                    'ghanapost_gps_address' => 'AB-111-2222',
                    'contact_person_name' => 'John Doe',
                    // business_name and tax_id should be null for family, and might be omitted by default Eloquent serialization
                    // Ensure you check the JSON structure if they are always included as null
                    'business_name' => null,
                    'tax_id' => null,
                ]
            ])
            ->assertJsonStructure([ // Verify the full JSON structure including dynamic fields
                'message',
                'customer' => [
                    'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                    'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                    'contact_person_phone', 'created_at', 'updated_at',
                ]
            ]);
    }

    /**
     * Test that an authenticated user cannot retrieve another user's customer profile.
     */
    public function test_authenticated_user_cannot_retrieve_another_users_customer_profile()
    {
        // Arrange: Create User A (authenticated) and their customer profile.
        $userA = $this->createAuthenticatedUser();
        $customerA = Customer::factory()->family()->create(['user_id' => $userA->id]);

        // Create User B and their customer profile (not authenticated for this request).
        $userB = User::factory()->create();
        $customerB = Customer::factory()->family()->create(['user_id' => $userB->id]);

        // Act: Authenticated User A tries to retrieve Customer B's profile.
        $response = $this->getJson("/api/customers/{$customerB->id}");

        // Assert: Expect 403 Forbidden due to authorization policy in UpdateCustomerRequest's authorize method.
        // The same authorize logic (or policy) should apply for SHOW.
        $response->assertStatus(403);
    }

    /**
     * Test that an unauthenticated user cannot retrieve any customer profile.
     */
    public function test_unauthenticated_user_cannot_retrieve_customer_profile()
    {
        // Arrange: Create a customer profile (owner doesn't matter for this test).
        $customer = Customer::factory()->create();

        // Act: Attempt to retrieve without being authenticated.
        $response = $this->getJson("/api/customers/{$customer->id}");

        // Assert: Expect 401 Unauthorized (due to 'auth:sanctum' middleware).
        $response->assertStatus(401);
    }

    /**
     * Test that retrieving a non-existent customer profile returns a 404 Not Found.
     */
    public function test_retrieving_non_existent_customer_profile_returns_404()
    {
        // Arrange: Authenticate a user.
        $this->createAuthenticatedUser();

        // Act: Attempt to retrieve a customer with a non-existent ID.
        $nonExistentId = 99999; // Assume this ID does not exist
        $response = $this->getJson("/api/customers/{$nonExistentId}");

        // Assert: Expect 404 Not Found (due to Route Model Binding).
        $response->assertStatus(404);
    }
}


