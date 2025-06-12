<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash; // Needed for creating test users

class AuthApiTest extends TestCase
{
    use RefreshDatabase, WithFaker; // Use RefreshDatabase for a clean slate for each test

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // You can optionally seed some roles or initial data if needed
        // For this, we'll rely on RefreshDatabase starting clean.
    }

    /**
     * Test successful user registration with email.
     *
     * @return void
     */
    public function test_user_can_register_with_email()
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201) // HTTP 201 Created
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'user' => ['id', 'name', 'email', 'phone_number', 'role'],
                     'token'
                 ])
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'User registered successfully',
                     'user' => [
                         'email' => $userData['email'],
                         'phone_number' => null, // Should be null if registered with email
                         'role' => 'customer'
                     ]
                 ]);

        // Assert user exists in the database
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'phone_number' => null,
            'role' => 'customer',
        ]);
    }

    /**
     * Test successful user registration with phone number.
     *
     * @return void
     */
    public function test_user_can_register_with_phone_number()
    {
        $userData = [
            'name' => $this->faker->name,
            'phone_number' => '+233202345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201) // HTTP 201 Created
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'user' => ['id', 'name', 'email', 'phone_number', 'role'],
                     'token'
                 ])
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'User registered successfully',
                     'user' => [
                         'email' => null, // Should be null if registered with phone
                         'phone_number' => $userData['phone_number'],
                         'role' => 'customer'
                     ]
                 ]);

        // Assert user exists in the database
        $this->assertDatabaseHas('users', [
            'email' => null,
            'phone_number' => $userData['phone_number'],
            'role' => 'customer',
        ]);
    }


    /**
     * Test user registration with missing name.
     *
     * @return void
     */
    public function test_user_cannot_register_without_name()
    {
        $userData = [
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422) // HTTP 422 Unprocessable Entity (Validation error)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test user registration with existing email.
     *
     * @return void
     */
    public function test_user_cannot_register_with_existing_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => $this->faker->name,
            'email' => 'existing@example.com', // Duplicate email
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test user registration with existing phone number.
     *
     * @return void
     */
    public function test_user_cannot_register_with_existing_phone_number()
    {
        User::factory()->create(['phone_number' => '+1234567890']);

        $userData = [
            'name' => $this->faker->name,
            'phone_number' => '+1234567890', // Duplicate phone number
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['phone_number']);
    }

    /**
     * Test user registration without email OR phone number.
     *
     * @return void
     */
    public function test_user_cannot_register_without_email_or_phone_number()
    {
        $userData = [
            'name' => $this->faker->name,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'phone_number']); // Should show errors for both
    }

    /**
     * Test successful user login with email.
     *
     * @return void
     */
    public function test_user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'login@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $credentials);

        $response->assertStatus(200) // HTTP 200 OK
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'user',
                     'token'
                 ])
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Login successful',
                     'user' => [
                         'email' => $user->email,
                     ]
                 ]);
    }

    /**
     * Test successful user login with phone number.
     *
     * @return void
     */
    public function test_user_can_login_with_phone_number()
    {
        $user = User::factory()->create([
            'phone_number' => '+233241234567',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'phone_number' => '+233241234567',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $credentials);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'user',
                     'token'
                 ])
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Login successful',
                     'user' => [
                         'phone_number' => $user->phone_number,
                     ]
                 ]);
    }

    /**
     * Test login with invalid credentials (wrong password).
     *
     * @return void
     */
    public function test_user_cannot_login_with_invalid_password()
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        $credentials = [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/login', $credentials);

        $response->assertStatus(401) // HTTP 401 Unauthorized
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Invalid credentials.'
                 ]);
    }

    /**
     * Test login with non-existent user.
     *
     * @return void
     */
    public function test_user_cannot_login_with_non_existent_email()
    {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $credentials);

        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Invalid credentials.'
                 ]);
    }

    /**
     * Test login without any identifier (email or phone).
     *
     * @return void
     */
    public function test_user_cannot_login_without_identifier()
    {
        $credentials = [
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $credentials);

        $response->assertStatus(422) // Validation error
                 ->assertJsonValidationErrors(['email', 'phone_number']); // Both should be reported missing
    }

    /**
     * Test retrieving authenticated user details using Sanctum::actingAs.
     *
     * @return void
     */
    public function test_can_get_authenticated_user_details()
    {
        // Arrange: Create a user and authenticate them using Sanctum::actingAs
        $user = User::factory()->create([
            'email' => 'authuser@example.com',
            'password' => Hash::make('password123'),
            'phone_number' => '+233202345678',
            'role' => 'customer',
        ]);

        // Sanctum::actingAs authenticates the user for the current test run.
        // No need to manually create token or set headers.
        Sanctum::actingAs($user, ['*']); // Grant all abilities for simplicity in this test

        // Act: Send a GET request to retrieve user details.
        $response = $this->getJson('/api/user');

        // Assert:
        $response->assertStatus(200) // HTTP 200 OK
                     ->assertJson([ // Expecting the raw user object directly
                         'id' => $user->id,
                         'name' => $user->name,
                         'email' => $user->email,
                         'phone_number' => $user->phone_number,
                         'role' => $user->role,
                     ])
                     ->assertJsonStructure([ // Structure also matches the raw object
                         'id', 'name', 'email', 'phone_number', 'role',
                         'email_verified_at', 'created_at', 'updated_at',
                     ]);
    }

    /**
     * Test guest cannot get authenticated user details. (This test remains unchanged)
     * @return void
     */
    public function test_guest_cannot_get_user_details()
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401)
                     ->assertJson([
                         'message' => 'Unauthenticated.'
                     ]);
    }

    /**
     * Test a user can logout successfully when authenticated via email. (This method was fixed above)
     * @return void
     */
    public function test_user_can_logout_with_email() // Renamed for clarity
    {
         // 1. Arrange: Register and log in a user to get a valid token.
        $user = User::factory()->create(['email' => 'logoutuser@example.com', 'password' => Hash::make('password')]);
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'logoutuser@example.com',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token, 'Token should not be empty after login');

        // 2. Act: Send a POST request to the logout endpoint with the token.
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        // 3. Assert: The logout itself should be successful (200 OK).
        $logoutResponse->assertStatus(200)
                       ->assertJson([
                           'message' => 'User logged out successfully',
                       ]);

        // Assert that the user token was actually deleted from the database.
        // This is the direct and most reliable way to confirm logout.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            // 'name' => 'auth_token' // If you named your tokens, you could add this.
        ]);

    }
    /**
     * Test a user can logout successfully when authenticated via phone number. (This method was fixed above)
     * @return void
     */
    public function test_user_can_logout_with_phone_number()
    {
        // 1. Arrange: Register and log in a user with a phone number to get a valid token.
        $phoneNumber = '+233201112222';
        $user = User::factory()->create([
            'phone_number' => $phoneNumber,
            'email' => null,
            'password' => Hash::make('password')
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'phone_number' => $phoneNumber,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token, 'Token should not be empty after phone number login');

        // 2. Act: Send a POST request to the logout endpoint with the token.
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        // 3. Assert: The logout itself should be successful (200 OK).
        $logoutResponse->assertStatus(200)
                       ->assertJson([
                           'message' => 'User logged out successfully',
                       ]);

        // Assert that the user token was actually deleted from the database.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Test admin user can access admin dashboard using Sanctum::actingAs.
     * @return void
     */
    public function test_admin_can_access_admin_dashboard()
    {
        // Arrange: Create an admin user and authenticate them using Sanctum::actingAs
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin', // Ensure this user has the admin role
        ]);
        Sanctum::actingAs($adminUser, ['*']); // Grant all abilities to admin

        // Act: Send a GET request to the admin dashboard.
        $response = $this->getJson('/api/admin/dashboard');

        // Assert:
        $response->assertStatus(200)
                     ->assertJson([
                         'message' => 'Welcome to the Admin Dashboard!'
                     ]);
    }

    /**
     * Test customer user cannot access admin dashboard using Sanctum::actingAs.
     * @return void
     */
    public function test_customer_cannot_access_admin_dashboard()
    {
        // Arrange: Create a customer user and authenticate them using Sanctum::actingAs
        $customerUser = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer', // Ensure this user has the customer role
        ]);
        Sanctum::actingAs($customerUser, ['*']); // Grant all abilities (for the token, not role check)

        // Act: Send a GET request to the admin dashboard.
        $response = $this->getJson('/api/admin/dashboard');

        // Assert:
        $response->assertStatus(403) // HTTP 403 Forbidden
                     ->assertJson([
                         'message' => 'Unauthorized. Admin access required.'
                     ]);
    }

    /**
     * Test unauthenticated user cannot access admin dashboard. (This test remains unchanged)
     * @return void
     */
    public function test_unauthenticated_cannot_access_admin_dashboard()
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(401)
                     ->assertJson([
                         'message' => 'Unauthenticated.'
                     ]);
    }
}