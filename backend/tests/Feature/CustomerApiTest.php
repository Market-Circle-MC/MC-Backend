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
        /** @var \App\Models\User $user */
        // Create a new user using the User factory. Factories provide dummy data.
        $user = User::factory()->create();

        // Simulate logging in as this user for the current test.
        // 'sanctum' is the API authentication guard typically used with Laravel Sanctum.
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function createAuthenticatedAdminUser(): User
    {
        /** @var \App\Models\User $admin */
        // Creates a user with the 'admin' role (using the 'admin' state from UserFactory).
        $admin = User::factory()->admin()->create();
        // Simulates logging in as this admin user using the 'sanctum' guard.
        $this->actingAs($admin, 'sanctum');
        return $admin;
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
    | CUSTOMER PROFILE RETRIEVAL (SINGLE) TESTS (HTTP Status: 200 OK / 401 Unauthorized / 403 Forbidden / 404 Not Found)
    |   -- HYBRID ACCESS (OWNER or ADMIN) --
    |--------------------------------------------------------------------------
    */

    /**
     * Test that an authenticated REGULAR user CAN retrieve their OWN customer profile by ID.
     */
    public function test_regular_user_can_retrieve_their_own_customer_profile()
    {
        // Arrange: Create a user and their customer profile.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create([
            'user_id' => $user->id,
            'ghanapost_gps_address' => 'AB-111-2222',
            'digital_address' => 'Agbogbloshie Market', // Assuming digital_address is not set in the factory
            'contact_person_name' => 'John Doe',
            'contact_person_phone' => '+233241234567',
        ]);

        // Act: Send a GET request to retrieve this customer profile.
        $response = $this->getJson("/api/customers/{$customer->id}");

        // Assert: Expect 200 OK and the customer's data.
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer profile retrieved successfully',
                'customer' => [
                    'id' => $customer->id,
                    'user_id' => $user->id,
                    'customer_type' => 'family',
                    'ghanapost_gps_address' => 'AB-111-2222',
                    'digital_address' => $customer->digital_address, // Assuming digital_address is not se
                    'contact_person_name' => 'John Doe',
                    'contact_person_phone' => $customer->contact_person_phone,
                    'business_name' => null,
                    'tax_id' => null,
                ]
            ])
            ->assertJsonStructure([
                'message',
                'customer' => [
                    'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                    'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                    'contact_person_phone', 'created_at', 'updated_at',
                ]
            ]);
    }

    /**
     * Test that an authenticated REGULAR user CANNOT retrieve another user's customer profile.
     * This test now expects a 403 Forbidden.
     */
    public function test_regular_user_cannot_retrieve_another_users_customer_profile()
    {
        // Arrange: Create User A (authenticated regular user) and their customer profile.
        $userA = $this->createAuthenticatedUser();
        Customer::factory()->family()->create(['user_id' => $userA->id]); // Customer for User A

        // Create User B and their customer profile.
        $userB = User::factory()->create();
        $customerB = Customer::factory()->family()->create(['user_id' => $userB->id]); // Customer for User B

        // Act: Authenticated User A tries to retrieve Customer B's profile.
        $response = $this->getJson("/api/customers/{$customerB->id}");

        // Assert: Expect 403 Forbidden.
        $response->assertStatus(403);
    }

    /**
     * Test that an ADMIN user CAN retrieve ANY customer profile by ID.
     */
    public function test_admin_can_retrieve_any_customer_profile_by_id()
    {
        // Arrange: Create an admin user.
        $admin = $this->createAuthenticatedAdminUser();

        // Create a regular user and their customer profile.
        $user = User::factory()->create();
        $customer = Customer::factory()->restaurant()->create([ // Use restaurant for different data
            'user_id' => $user->id,
            'business_name' => 'Admin View Test Co.',
            'tax_id' => 'V0011223344',
            'ghanapost_gps_address' => 'GT-123-4567',
            'digital_address' => 'Digital Address Example',
            'contact_person_name' => 'Admin Contact',
            'contact_person_phone' => '+233501234567',
        ]);

        // Act: Admin user tries to retrieve the customer profile.
        $response = $this->getJson("/api/customers/{$customer->id}");

        // Assert: Expect 200 OK and the customer's data.
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Customer profile retrieved successfully',
                'customer' => [
                    'id' => $customer->id,
                    'user_id' => $user->id,
                    'customer_type' => 'restaurant',
                    'business_name' => 'Admin View Test Co.',
                    'ghanapost_gps_address' => $customer->ghanapost_gps_address,
                    'digital_address' => $customer->digital_address,
                    'tax_id' => 'V0011223344',
                    'contact_person_name' => $customer->contact_person_name,
                    'contact_person_phone' => $customer->contact_person_phone,
                ]
            ])
            ->assertJsonStructure([
                'message',
                'customer' => [
                    'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                    'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                    'contact_person_phone', 'created_at', 'updated_at',
                ]
            ]);
    }

    /**
     * Test that an unauthenticated user cannot retrieve any customer profile by ID.
     */
    public function test_unauthenticated_user_cannot_retrieve_customer_profile_by_id()
    {
        // Arrange: Create a customer profile.
        $customer = Customer::factory()->create();

        // Act: Attempt to retrieve without being authenticated.
        $response = $this->getJson("/api/customers/{$customer->id}");

        // Assert: Expect 401 Unauthorized.
        $response->assertStatus(401);
    }

    /**
     * Test that retrieving a non-existent customer profile returns a 404 Not Found.
     * This applies to both regular and admin users as Route Model Binding handles it.
     */
    public function test_retrieving_non_existent_customer_profile_returns_404()
    {
        // Arrange: Authenticate a user (doesn't matter if regular or admin, 404 comes first).
        $this->createAuthenticatedUser(); // Can use createAuthenticatedAdminUser too if you want to test admin 404.

        // Act: Attempt to retrieve a customer with a non-existent ID.
        $nonExistentId = 99999;
        $response = $this->getJson("/api/customers/{$nonExistentId}");

        // Assert: Expect 404 Not Found (due to Route Model Binding).
        $response->assertStatus(404);
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PROFILE LIST RETRIEVAL TESTS (HTTP Status: 200 OK / 401 Unauthorized / 403 Forbidden)
    |   -- HYBRID ACCESS (OWNER or ADMIN) --
    |--------------------------------------------------------------------------
    */

    /**
     * Test that an authenticated REGULAR user can retrieve their OWN customer profile in the list.
     * They should NOT see other users' profiles.
     */
    public function test_regular_user_gets_only_their_customer_profile_in_list()
    {
        // Arrange: Create a user and their customer profile.
        $user = $this->createAuthenticatedUser();
        $customer = Customer::factory()->family()->create([
            'user_id' => $user->id,
            'ghanapost_gps_address' => 'AB-111-LIST',
            'contact_person_name' => 'List User',
        ]);

        // Create other customers for other users (should NOT be returned for the authenticated user).
        Customer::factory()->count(2)->create();

        // Act: Send a GET request to the list endpoint.
        $response = $this->getJson('/api/customers');

        // Assert:
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Your customer profile retrieved successfully', // Check for the regular user's success message
                'customers' => [
                    // Only the authenticated user's customer profile should be in the list.
                    [
                        'id' => $customer->id,
                        'user_id' => $user->id,
                        'customer_type' => 'family',
                        'ghanapost_gps_address' => 'AB-111-LIST',
                        'contact_person_name' => 'List User',
                        'business_name' => null,
                        'tax_id' => null,
                    ]
                ]
            ])
            ->assertJsonStructure([
                'message',
                'customers' => [
                    '*' => [ // '*' means any item in the array
                        'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                        'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                        'contact_person_phone', 'created_at', 'updated_at',
                    ]
                ]
            ]);

        // Also assert that the count of customers returned is exactly 1 (for the current user).
        $this->assertCount(1, $response->json('customers'));
    }

    /**
     * Test that an authenticated REGULAR user without a customer profile gets an empty list.
     */
    public function test_regular_user_without_customer_profile_gets_empty_list()
    {
        // Arrange: Create an authenticated user but NO customer profile for them.
        $this->createAuthenticatedUser();

        // Create some other customers for other users (should NOT be returned).
        Customer::factory()->count(3)->create();

        // Act: Send a GET request to the list endpoint.
        $response = $this->getJson('/api/customers');

        // Assert:
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'No customer profile found for the authenticated user',
                'customers' => [] // Expect an empty array
            ])
            ->assertJsonStructure([
                'message',
                'customers', // Ensure 'customers' key exists, even if array is empty
            ]);

        // Assert that the count of customers returned is exactly 0.
        $this->assertCount(0, $response->json('customers'));
    }

    /**
     * Test that an ADMIN user can retrieve a list of all customer profiles.
     */
    public function test_admin_can_retrieve_all_customer_profiles_list()
    {
         /** @var \App\Models\User $admin */
        // Arrange: Create an admin user.
        $admin = $this->createAuthenticatedAdminUser();

        // Create several customer profiles for different users.
        /** @var \App\Models\Customer $customer1 */
        $customer1 = Customer::factory()->family()->create(['user_id' => User::factory()->create()->id]);
        /** @var \App\Models\Customer $customer2 */
        $customer2 = Customer::factory()->restaurant()->create(['user_id' => User::factory()->create()->id]);
        /** @var \App\Models\Customer $customer3 */
        $customer3 = Customer::factory()->individualBulk()->create(['user_id' => User::factory()->create()->id]);


        // Act: Send a GET request to the list endpoint.
        $response = $this->getJson('/api/customers');

        // Assert:
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'All customer profiles retrieved successfully',
                'customers' => [
                    'current_page' => 1, // Assuming default pagination
                    'data' => [
                        // Verify the presence of expected customer IDs
                        ['id' => $customer1->id],
                        ['id' => $customer2->id],
                        ['id' => $customer3->id],
                    ],
                    'per_page' => 15, // Default per_page value
                    'total' => 3,
                ]
            ]) 
            ->assertJsonStructure([
                'message',
                'customers' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id', 'user_id', 'customer_type', 'business_name', 'tax_id',
                            'ghanapost_gps_address', 'digital_address', 'contact_person_name',
                            'contact_person_phone', 'created_at', 'updated_at',
                    ]
                        ],
                        'first_page_url',
                        'from',
                        'last_page',
                        'last_page_url',
                        'links',
                        'next_page_url',
                        'path',
                        'per_page',
                        'prev_page_url',
                        'to',
                        'total',
                    ]
            ]);

        $this->assertCount(3, $response->json('customers.data')); // Should return all 3 created customers
    }

    /**
     * Test that an unauthenticated user cannot retrieve the customer list.
     */
    public function test_unauthenticated_user_cannot_retrieve_customer_list()
    {
        // Arrange: Create some customer profiles.
        Customer::factory()->count(2)->create();

        // Act: Attempt to retrieve the list without being authenticated.
        $response = $this->getJson('/api/customers');

        // Assert: Expect 401 Unauthorized (due to 'auth:sanctum' middleware).
        $response->assertStatus(401);
    }

 /*
    |--------------------------------------------------------------------------
    | CUSTOMER PROFILE DELETION TESTS (HTTP Status: 200 OK / 401 Unauthorized / 403 Forbidden / 404 Not Found)
    |   -- HYBRID ACCESS (OWNER or ADMIN) --
    |--------------------------------------------------------------------------
    */

    /**
     * Test that an authenticated REGULAR user can delete their OWN customer profile.
     */
    public function test_regular_user_can_delete_their_own_customer_profile()
    {
        // Arrange: Create a user and their customer profile.
        /** @var \App\Models\User $user */
        $user = $this->createAuthenticatedUser();
        /** @var \App\Models\Customer $customer */
        $customer = Customer::factory()->family()->create(['user_id' => $user->id]);

        // Assert that the customer exists in the database initially.
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        // Act: Send a DELETE request to delete this customer profile.
        $response = $this->deleteJson("/api/customers/{$customer->id}");

        // Assert:
        $response->assertStatus(200) // Expect 200 OK (with message) or 204 No Content if chosen.
                 ->assertJson([
                     'message' => 'Customer profile deleted successfully.'
                 ]);

        // Assert that the customer record is no longer in the database.
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * Test that an authenticated REGULAR user CANNOT delete another user's customer profile.
     */
    public function test_regular_user_cannot_delete_another_users_customer_profile()
    {
        // Arrange: Create User A (authenticated regular user) and their customer profile.
        /** @var \App\Models\User $userA */
        $userA = $this->createAuthenticatedUser();
        Customer::factory()->family()->create(['user_id' => $userA->id]); // Customer for User A

        // Create User B and their customer profile.
        /** @var \App\Models\User $userB */
        $userB = User::factory()->create();
        /** @var \App\Models\Customer $customerB */
        $customerB = Customer::factory()->family()->create(['user_id' => $userB->id]);

        // Assert that Customer B exists initially.
        $this->assertDatabaseHas('customers', ['id' => $customerB->id]);

        // Act: Authenticated User A tries to delete Customer B's profile.
        $response = $this->deleteJson("/api/customers/{$customerB->id}");

        // Assert: Expect 403 Forbidden.
        $response->assertStatus(403);

        // Assert that Customer B's profile still exists in the database (was NOT deleted).
        $this->assertDatabaseHas('customers', ['id' => $customerB->id]);
    }

    /**
     * Test that an ADMIN user CAN delete ANY customer profile.
     */
    public function test_admin_can_delete_any_customer_profile()
    {
        // Arrange: Create an admin user.
        /** @var \App\Models\User $admin */
        $admin = $this->createAuthenticatedAdminUser();

        // Create a regular user and their customer profile (that the admin will delete).
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        /** @var \App\Models\Customer $customer */
        $customer = Customer::factory()->restaurant()->create(['user_id' => $user->id]);

        // Assert that the customer exists in the database initially.
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        // Act: Admin user tries to delete the customer profile.
        $response = $this->deleteJson("/api/customers/{$customer->id}");

        // Assert:
        $response->assertStatus(200) // Expect 200 OK (with message) or 204 No Content.
                 ->assertJson([
                     'message' => 'Customer profile deleted successfully.'
                 ]);

        // Assert that the customer record is no longer in the database.
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * Test that an unauthenticated user cannot delete any customer profile.
     */
    public function test_unauthenticated_user_cannot_delete_customer_profile()
    {
        // Arrange: Create a customer profile.
        /** @var \App\Models\Customer $customer */
        $customer = Customer::factory()->create();

        // Assert that the customer exists initially.
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        // Act: Attempt to delete without being authenticated.
        $response = $this->deleteJson("/api/customers/{$customer->id}");

        // Assert: Expect 401 Unauthorized.
        $response->assertStatus(401);

        // Assert that the customer record still exists (was NOT deleted).
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    /**
     * Test that deleting a non-existent customer profile returns a 404 Not Found.
     * This applies to both regular and admin users as Route Model Binding handles it.
     */
    public function test_deleting_non_existent_customer_profile_returns_404()
    {
        // Arrange: Authenticate a user (regular or admin, 404 comes first).
        $this->createAuthenticatedUser();

        // Act: Attempt to delete a customer with a non-existent ID.
        $nonExistentId = 99999;
        $response = $this->deleteJson("/api/customers/{$nonExistentId}");

        // Assert: Expect 404 Not Found (due to Route Model Binding).
        $response->assertStatus(404);
    }
}


