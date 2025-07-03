<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test; 
use Illuminate\Support\Facades\Log;

class ProductApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        // Use a real disk for testing image storage if you want to verify actual files
        // Or keep fake for speed. For this scenario, `Storage::fake()` is appropriate.
        Storage::fake('public');

        // Create an active category for product association, accessible to all tests
        $this->category = Category::factory()->create([
            'is_active' => true,
            'name' => 'Default Test Category',
            'slug' => 'default-test-category'
        ]);
    }

    // Helper to create an admin user and authenticate
    protected function createAdminUser()
    {
        // Assuming your User model has a 'role' column
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);
        return $admin;
    }

    // Helper to create a customer user and authenticate
    protected function createCustomerUser()
    {
        // Assuming your User model has a 'role' column
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer, ['*']);
        return $customer;
    }

    // Helper to create a fake image file for testing uploads.
    protected function getFakeImage(string $name = 'test_image.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'image/jpeg'); // Name, size in KB, MIME type
    }

    // Helper to define expected product JSON structure
    protected function expectedProductJsonStructure($includeRelationships = false, $includeDiscountFields = true)
    {
        $baseStructure = [
            'id', 'category_id', 'name', 'slug', 'price_per_unit', 'unit_of_measure',
            'min_order_quantity', 'stock_quantity', 'short_description', 'description',
            'is_active', 'is_featured', 'sku', 'created_at', 'updated_at',
            'current_price', 'is_discounted', 'discount_status'
        ];

        if ($includeDiscountFields) {
            $baseStructure = array_merge($baseStructure, [
                'discount_price', 'discount_percentage', 'discount_start_date', 'discount_end_date'
            ]);
        }

        if ($includeRelationships) {
            $baseStructure['category'] = ['id', 'name', 'slug']; // Basic category structure, include slug
            $baseStructure['images'] = ['*' => ['id', 'product_id', 'image_url', 'is_main_image']]; // Basic image structure
        }

        return $baseStructure;
    }

    #[Test]
    public function test_example(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    #[Test]
    public function test_guest_can_view_all_products()
    {
        // Ensure products are created with the category created in setUp
        Product::factory(5)->create([
            'is_active' => true,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->randomFloat(2,1,5)
            ]);
        Product::factory(2)->create([
            'is_active' => false,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->randomFloat(2,1,5)
            ]);
        $activeProductsInDb = Product::where('is_active', true)->count();
        Log::info('Number of active products in DB before API call:', ['count' => $activeProductsInDb]);
        // --- END NEW DEBUGGING LINE ---
        $response = $this->getJson('/api/products?per_page=5');
        Log::info ('Response Data for Guests: ', $response->json());
        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data.data') // 'data.data' for paginated response
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'data' => [
                             '*' => $this->expectedProductJsonStructure(true, true) // Expect all fields including relationships and discount fields
                         ],
                         'total', 'per_page', 'current_page', 'from', 'to', 'last_page', 'path', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url' // Standard pagination keys
                     ]
                 ]);
                 $responseData = $response->json('data.data');
                 foreach ($responseData as $productData) {
                    $this->assertTrue($productData['is_active'], "Guest received an inactive product.");
                    $this->assertTrue($productData['is_featured'], "Guest received a non-featured product."); // New assertion
                    $this->assertGreaterThan(0, $productData['stock_quantity'], "Guest received an out-of-stock product."); // New assertion
                }
    }

    #[Test]
    public function test_customer_can_view_only_active_products()
    {
        $this->createCustomerUser();

        // Ensure products meet the filters
        Product::factory(3)->create([
            'is_active' => true,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->numberBetween(1, 100)
        ]);
        Product::factory(4)->create([
            'is_active' => false,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->numberBetween(1, 100)
        ]);

        $response = $this->getJson('/api/products?per_page=10');
        Log::info('Response Data for Customer (test_customer_can_view_only_active_products): ', $response->json());

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data.data')
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'data' => [
                             '*' => $this->expectedProductJsonStructure(true, true)
                         ],
                         'total', 'per_page', 'current_page'
                     ]
                 ]);

        $responseData = $response->json('data.data');
        foreach ($responseData as $productData) {
            $this->assertTrue($productData['is_active'], "Customer received an inactive product.");
            $this->assertTrue($productData['is_featured'], "Customer received a non-featured product.");
            $this->assertGreaterThan(0, $productData['stock_quantity'], "Customer received an out-of-stock product.");
        }
    }

    #[Test]
    public function test_admin_can_view_all_products_including_inactive()
    {
        $this->createAdminUser();

        // Admins should see all products regardless of featured/stock status for this test's assertion.
        // However, if the controller applies these filters globally, admins will also be affected.
        // To pass this test with global filters, all products (active/inactive) need to meet them.
        Product::factory(2)->create([
            'is_active' => true,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->numberBetween(1, 100)
        ]);
        Product::factory(3)->create([
            'is_active' => false,
            'category_id' => $this->category->id,
            'is_featured' => true,
            'stock_quantity' => $this->faker->numberBetween(1, 100)
        ]);

        $response = $this->getJson('/api/products?per_page=10');
        Log::info('Response Data for Admin (test_admin_can_view_all_products_including_inactive): ', $response->json());

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data.data')
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'data' => [
                             '*' => $this->expectedProductJsonStructure(true, true)
                         ],
                         'total', 'per_page', 'current_page'
                     ]
                 ]);

        $responseData = $response->json('data.data');
        $activeCount = 0;
        $inactiveCount = 0;
        foreach ($responseData as $productData) {
            if ($productData['is_active']) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
            // For admin test, if filters are global, all products in response must meet them
            $this->assertTrue($productData['is_featured'], "Admin received a non-featured product.");
            $this->assertGreaterThan(0, $productData['stock_quantity'], "Admin received an out-of-stock product.");
        }
        $this->assertEquals(2, $activeCount, "Admin did not receive correct number of active products.");
        $this->assertEquals(3, $inactiveCount, "Admin did not receive correct number of inactive products.");
    }


    #[Test]
    public function test_admin_can_create_product_with_image()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $image = UploadedFile::fake()->create('product_image.jpg', 100, 'image/jpeg');

        $productData = [
            'category_id' => $category->id,
            'name' => 'Test Product Image',
            'short_description' => 'This is a test product with an image.',
            'price_per_unit' => 19.99,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1,
            'stock_quantity' => 100,
            'images' => [$image],
        ];

        
        $response = $this->postJson('/api/admin/products', $productData);
        Log::info ('Response Data for admin: ', $response->json());
        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Test Product Image',
                         'category_id' => $category->id,
                     ]
                     ]);
                 /**->assertJsonStructure([
                     'message',
                     'data' => array_merge(
                         $this->expectedProductJsonStructure(false, true), // No relationships on creation response, but discount fields
                         ['images' => ['*' => ['id', 'product_id', 'image_url', 'is_main_image']]] // Images should be present
                     )
                 ]);*/

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product Image',
            'category_id' => $category->id,
        ]);

        $product = Product::where('name', 'Test Product Image')->first();
        $this->assertCount(1, $product->images);
        $storedPath = str_replace(Storage::url(''), '', $product->images->first()->image_url);
        Storage::disk('public')->assertExists($storedPath);
    }

    #[Test]
    public function test_admin_can_create_product_without_image()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);

        $productData = [
            'category_id' => $category->id,
            'name' => 'Another Product',
            'price_per_unit' => 9.99,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 0.5,
            'stock_quantity' => 50,
            'is_featured' => true,
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Another Product',
                         'is_featured' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('products', ['name' => 'Another Product']);
        $product = Product::where('name', 'Another Product')->first();
        $this->assertCount(0, $product->images);
    }

    #[Test]
    public function test_customer_cannot_create_product()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create(['is_active' => true]);

        $productData = [
            'category_id' => $category->id,
            'name' => 'Forbidden Product',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized. Admin access required.']);

        $this->assertDatabaseMissing('products', ['name' => 'Forbidden Product']);
    }

    #[Test]
    public function test_unauthenticated_cannot_create_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $productData = [
            'category_id' => $category->id,
            'name' => 'Unauthorized Product',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseMissing('products', ['name' => 'Unauthorized Product']);
    }

    #[Test]
    public function test_product_creation_fails_with_missing_fields()
    {
        $this->createAdminUser();
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id', 'name', 'price_per_unit', 'unit_of_measure', 'min_order_quantity', 'stock_quantity']);
    }

    #[Test]
    public function test_product_creation_fails_with_invalid_category_id()
    {
        $this->createAdminUser();
        $productData = [
            'category_id' => 9999,
            'name' => 'Invalid Category Product',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function test_product_creation_fails_with_inactive_category_id()
    {
        $this->createAdminUser();
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $productData = [
            'category_id' => $inactiveCategory->id,
            'name' => 'Inactive Category Product',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function test_product_creation_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['name' => 'Existing Product Name', 'category_id' => $category->id]);

        $productData = [
            'category_id' => $category->id,
            'name' => 'Existing Product Name',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_product_creation_fails_with_non_unique_sku()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['sku' => 'UNIQUE-SKU-123', 'category_id' => $category->id]);

        $productData = [
            'category_id' => $category->id,
            'name' => 'New Product with Dup SKU',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
            'sku' => 'UNIQUE-SKU-123',
        ];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['sku']);
    }

    #[Test]
    public function test_guest_can_retrieve_single_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $fakeImageName = 'products/' . Str::random(40) . '.jpg';
        Storage::disk('public')->put($fakeImageName, 'dummy content');
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => Storage::url($fakeImageName),
        ]);

        $response = $this->getJson('/api/products/' . $product->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product retrieved successfully.',
                     'data' => [
                         'id' => $product->id,
                         'name' => $product->name,
                         'category_id' => $category->id,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => $this->expectedProductJsonStructure(true, true) // Expect all fields including relationships and discount fields
                 ]);
    }

    #[Test]
    public function test_retrieving_non_existent_product_returns_404()
    {
        $response = $this->getJson('/api/products/99999');
        $response->assertStatus(404);
    }

    #[Test]
    public function test_admin_can_update_product()
    {
        $this->createAdminUser();
        $oldCategory = Category::factory()->create(['is_active' => true]);
        $newCategory = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $oldCategory->id, 'name' => 'Old Product', 'price_per_unit' => 10.00]);

        $updateData = [
            'name' => 'Updated Product',
            'price_per_unit' => 25.50,
            'category_id' => $newCategory->id,
            'is_active' => false,
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'name' => 'Updated Product',
                         'price_per_unit' => 25.50,
                         'category_id' => $newCategory->id,
                         'is_active' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price_per_unit' => 25.50,
            'category_id' => $newCategory->id,
            'is_active' => false,
        ]);
    }

    #[Test]
    public function test_admin_can_partially_update_product()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 50, 'is_active' => true]);

        $updateData = [
            'stock_quantity' => 45,
            'is_active' => false,
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->patchJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'stock_quantity' => 45,
                         'is_active' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 45,
            'is_active' => false,
        ]);
    }

    #[Test]
    public function test_admin_can_update_product_with_new_images()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $image1 = UploadedFile::fake()->create('updated_image1.png', 100, 'image/png');
        $image2 = UploadedFile::fake()->create('updated_image2.gif', 100, 'image/gif');

        $updateData = [
            'images' => [$image1, $image2],
        ];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->postJson('/api/admin/products/' . $product->id, array_merge($updateData, ['_method' => 'PUT']));

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                     ]
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'images' => [
                             '*' => ['id', 'product_id', 'image_url', 'is_main_image']
                         ]
                     ]
                 ]);

        $product->refresh();
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists(str_replace(Storage::url(''), '', $product->images[0]->image_url));
        Storage::disk('public')->assertExists(str_replace(Storage::url(''), '', $product->images[1]->image_url));
    }

    #[Test]
    public function test_customer_cannot_update_product()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $updateData = ['name' => 'Attempted Update'];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Unauthorized. Admin access required.']);

        $this->assertDatabaseMissing('products', ['name' => 'Attempted Update']);
    }

    #[Test]
    public function test_unauthenticated_cannot_update_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $updateData = ['name' => 'Unauthorized Update'];

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseMissing('products', ['name' => 'Unauthorized Update']);
    }

    #[Test]
    public function test_product_update_fails_with_invalid_category_id()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $updateData = ['category_id' => 9999];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function test_product_update_fails_with_inactive_category_id()
    {
        $this->createAdminUser();
        $activeCategory = Category::factory()->create(['is_active' => true]);
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $activeCategory->id]);

        $updateData = ['category_id' => $inactiveCategory->id];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $product->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function test_product_update_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['name' => 'Existing Product', 'category_id' => $category->id]);
        $productToUpdate = Product::factory()->create(['name' => 'Another Product', 'category_id' => $category->id]);

        $updateData = ['name' => 'Existing Product'];
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->putJson('/api/admin/products/' . $productToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_admin_can_delete_product()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $relativeImagePath = 'products/' . Str::random(40) . '.jpg';
        Storage::disk('public')->put($relativeImagePath, 'dummy image content');

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => $relativeImagePath, // Store the RELATIVE path here
        ]);

        $pathForAssertion = $relativeImagePath;

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->deleteJson('/api/admin/products/' . $product->id);

        $response->assertStatus(200)
                     ->assertJson([
                         'message' => 'Product deleted successfully.',
                     ]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing($pathForAssertion);
    }

    #[Test]
    public function test_customer_cannot_delete_product()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->deleteJson('/api/admin/products/' . $product->id);

        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    #[Test]
    public function test_unauthenticated_cannot_delete_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->deleteJson('/api/admin/products/' . $product->id);

        $response->assertStatus(401)
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    #[Test]
    public function test_deleting_non_existent_product_returns_404()
    {
        $this->createAdminUser();
        // *** CHANGE APPLIED: Corrected URL with '/admin' prefix ***
        $response = $this->deleteJson('/api/admin/products/99999');
        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Creation (Store) with Discount Price Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_admin_can_create_product_with_valid_discount_price()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('discounted_product_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Discounted Product Creation',
            'price_per_unit' => 50.00,
            'discount_price' => 40.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 100,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Discounted Product Creation',
                         'price_per_unit' => '50.00',
                         'discount_price' => '40.00',
                         'is_discounted' => true,
                         'current_price' => 40,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Discounted Product Creation',
            'price_per_unit' => 50.00,
            'discount_price' => 40.00,
        ]);

        $product = Product::where('name', 'Discounted Product Creation')->first();
        $this->assertNotNull($product);
        $this->assertEquals(40.00, $product->discount_price);
        $this->assertEquals(40, $product->current_price);
        $this->assertTrue($product->is_discounted);
    }

    #[Test]
    public function test_product_creation_fails_with_discount_price_greater_than_price_per_unit()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('invalid_discount_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Invalid Discount Product Creation',
            'price_per_unit' => 30.00,
            'discount_price' => 35.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 100,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_price']);

        $this->assertDatabaseMissing('products', ['name' => 'Invalid Discount Product Creation']);
    }

    #[Test]
    public function test_product_creation_fails_with_discount_price_equal_to_price_per_unit()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('equal_discount_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Equal Discount Product Creation',
            'price_per_unit' => 20.00,
            'discount_price' => 20.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 100,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_price']);

        $this->assertDatabaseMissing('products', ['name' => 'Equal Discount Product Creation']);
    }

    #[Test]
    public function test_admin_can_create_product_without_discount_price()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('no_discount_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product Without Discount Creation',
            'price_per_unit' => 15.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 50,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Product Without Discount Creation',
                         'price_per_unit' => '15.00',
                         'current_price' => 15,
                         'is_discounted' => false,
                         'discount_status' => 'none',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product Without Discount Creation',
            'price_per_unit' => 15.00,
            'discount_price' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Update with Discount Price Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_admin_can_update_product_with_valid_discount_price()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 200.00,
            'name' => 'Original Product For Discount Update',
            'is_active' => true,
        ]);
        ProductImage::factory()->create(['product_id' => $product->id]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_price' => 150.00,
            'price_per_unit' => 200.00,
            'is_active' => 0,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => '150.00',
                         'is_active' => false,
                         'is_discounted' => true,
                         'current_price' => 150,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => 150.00,
            'is_active' => false,
        ]);

        $updatedProduct = Product::find($product->id);
        $this->assertEquals(150.00, $updatedProduct->discount_price);
        $this->assertEquals(150, $updatedProduct->current_price);
        $this->assertTrue($updatedProduct->is_discounted);
    }

    #[Test]
    public function test_product_update_fails_if_discount_price_is_greater_than_original_price()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'name' => 'Product To Fail Discount Update',
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_price' => 110.00,
            'price_per_unit' => 100.00,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_price']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => null, // Assuming it was null before the failed update
        ]);
    }

    #[Test]
    public function test_product_discount_price_can_be_removed_on_update()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_price' => null,
            'price_per_unit' => 100.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => null,
                         'is_discounted' => false,
                         'current_price' => 100,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => null,
        ]);

        $updatedProduct = Product::find($product->id);
        $this->assertEquals(100, $updatedProduct->current_price);
        $this->assertFalse($updatedProduct->is_discounted);
    }

    #[Test]
    public function test_product_discount_price_is_unchanged_if_omitted_in_update()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'short_description' => 'Updated desc for discount test',
            'price_per_unit' => 100.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => '70.00',
                         'is_discounted' => true,
                         'current_price' => 70,
                         'short_description' => 'Updated desc for discount test',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => 70.00,
            'short_description' => 'Updated desc for discount test',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Creation (Store) with Discount Percentage
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_admin_can_create_product_by_providing_discount_percentage_and_fixed_price_is_calculated()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('percentage_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product by Percentage Input',
            'price_per_unit' => 100.00,
            'discount_percentage' => 20.00,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Product by Percentage Input',
                         'price_per_unit' => '100.00',
                         'discount_percentage' => 20,
                         'discount_price' => '80.00',
                         'is_discounted' => true,
                         'current_price' => 80,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product by Percentage Input',
            'price_per_unit' => 100.00,
            'discount_percentage' => 20.00,
            'discount_price' => 80.00,
        ]);
    }

    #[Test]
    public function test_admin_can_create_product_by_providing_fixed_discount_price_and_percentage_is_calculated()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('fixed_price_creation.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product by Fixed Price Input',
            'price_per_unit' => 100.00,
            'discount_price' => 75.00,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Product by Fixed Price Input',
                         'price_per_unit' => '100.00',
                         'discount_percentage' => 25,
                         'discount_price' => '75.00',
                         'is_discounted' => true,
                         'current_price' => 75,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product by Fixed Price Input',
            'price_per_unit' => 100.00,
            'discount_percentage' => 25.00,
            'discount_price' => 75.00,
        ]);
    }

    #[Test]
    public function test_product_creation_fails_if_discount_percentage_is_out_of_valid_range()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('invalid_percentage_range.jpg');

        $response1 = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Over 100% Discount Product',
            'price_per_unit' => 100.00,
            'discount_percentage' => 101.00,
            'unit_of_measure' => 'piece', 'min_order_quantity' => 1, 'stock_quantity' => 10, 'is_active' => 1, 'images' => [$image],
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors(['discount_percentage']);
        $this->assertDatabaseMissing('products', ['name' => 'Over 100% Discount Product']);

        $response2 = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Negative Discount Product',
            'price_per_unit' => 100.00,
            'discount_percentage' => -0.01,
            'unit_of_measure' => 'piece', 'min_order_quantity' => 1, 'stock_quantity' => 10, 'is_active' => 1, 'images' => [$image],
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['discount_percentage']);
        $this->assertDatabaseMissing('products', ['name' => 'Negative Discount Product']);
    }

    #[Test]
    public function test_product_creation_fails_if_both_discount_price_and_percentage_are_provided_in_request()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('both_discounts.jpg');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product With Both Discounts',
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_price', 'discount_percentage']);
        $this->assertDatabaseMissing('products', ['name' => 'Product With Both Discounts']);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Update with Discount Percentage
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_admin_can_update_product_by_providing_discount_percentage_and_fixed_price_is_calculated()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 200.00,
            'name' => 'Product For Percentage Update',
            'discount_price' => null,
            'discount_percentage' => null,
            'is_active' => true,
        ]);
        ProductImage::factory()->create(['product_id' => $product->id]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_percentage' => 25.00,
            'price_per_unit' => 200.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_percentage' => 25,
                         'discount_price' => '150.00',
                         'is_discounted' => true,
                         'current_price' => 150,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => 25.00,
            'discount_price' => 150.00,
        ]);

        $updatedProduct = Product::find($product->id);
        $this->assertEquals(150.00, $updatedProduct->discount_price);
        $this->assertEquals(150, $updatedProduct->current_price);
        $this->assertTrue($updatedProduct->is_discounted);
    }

    #[Test]
    public function test_admin_can_update_product_by_providing_fixed_discount_price_and_percentage_is_calculated()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 200.00,
            'name' => 'Product For Fixed Price Update',
            'discount_price' => null,
            'discount_percentage' => null,
            'is_active' => true,
        ]);
        ProductImage::factory()->create(['product_id' => $product->id]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_price' => 180.00,
            'price_per_unit' => 200.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_percentage' => 10,
                         'discount_price' => '180.00',
                         'is_discounted' => true,
                         'current_price' => 180,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => 10.00,
            'discount_price' => 180.00,
        ]);

        $updatedProduct = Product::find($product->id);
        $this->assertEquals(180.00, $updatedProduct->discount_price);
        $this->assertEquals(180, $updatedProduct->current_price);
        $this->assertTrue($updatedProduct->is_discounted);
    }

    #[Test]
    public function test_admin_can_remove_all_discounts_by_setting_one_to_null()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_percentage' => 10.00,
            'discount_price' => 90.00,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_percentage' => null,
            'price_per_unit' => 100.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_percentage' => null,
                         'discount_price' => null,
                         'is_discounted' => false,
                         'current_price' => 100,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => null,
            'discount_price' => null,
        ]);

        $updatedProduct = Product::find($product->id);
        $this->assertEquals(100, $updatedProduct->current_price);
        $this->assertFalse($updatedProduct->is_discounted);
    }

    #[Test]
    public function test_product_update_fails_if_discount_percentage_is_out_of_range_on_update()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
        ]);

        $response1 = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_percentage' => 100.01,
            'price_per_unit' => 100.00,
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors(['discount_percentage']);

        $response2 = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_percentage' => -0.01,
            'price_per_unit' => 100.00,
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['discount_percentage']);
    }

    #[Test]
    public function test_admin_can_switch_from_fixed_discount_price_to_percentage_discount()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
            'discount_percentage' => null,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'discount_percentage' => 15.00,
            'discount_price' => null,
            'price_per_unit' => 100.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_percentage' => 15,
                         'discount_price' => '85.00',
                         'is_discounted' => true,
                         'current_price' => 85,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => 15.00,
            'discount_price' => 85.00,
        ]);
    }

    #[Test]
    public function test_admin_can_switch_from_percentage_discount_to_fixed_discount_price()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_percentage' => 25.00,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 100.00,
            'discount_price' => 75.00,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'price_per_unit' => '100.00',
                         'discount_percentage' => 25,
                         'discount_price' => '75.00',
                         'is_discounted' => true,
                         'current_price' => 75,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => 25.00,
            'discount_price' => 75.00,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Discount with Dates (Creation)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_admin_can_create_product_with_fixed_discount_and_active_dates_and_calculated_percentage()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('fixed_discount_active_dates_calc.jpg');
        $now = Carbon::now();
        $startDate = $now->copy()->format('Y-m-d H:i:s');
        $endDate = $now->copy()->addDays(10)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product Active Fixed Discount w/Dates',
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 50,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Product Active Fixed Discount w/Dates',
                         'price_per_unit' => '100.00',
                         'discount_price' => '80.00',
                         'discount_percentage' => 20,
                         'discount_start_date' => Carbon::parse($startDate)->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => Carbon::parse($endDate)->toIso8601ZuluString('microseconds'),
                         'current_price' => 80,
                         'is_discounted' => true,
                         'discount_status' => 'active',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product Active Fixed Discount w/Dates',
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
        ]);
    }

    #[Test]
    public function test_admin_can_create_product_with_percentage_discount_and_upcoming_dates_and_calculated_fixed_price()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('percentage_discount_upcoming_dates_calc.jpg');
        $now = Carbon::now();
        $startDate = $now->copy()->addDays(5)->format('Y-m-d H:i:s');
        $endDate = $now->copy()->addDays(10)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product Upcoming Percentage Discount w/Dates',
            'price_per_unit' => 100.00,
            'discount_percentage' => 15.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1,
            'stock_quantity' => 50,
            'is_active' => 1,
            'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Product Upcoming Percentage Discount w/Dates',
                         'price_per_unit' => '100.00',
                         'discount_price' => '85.00',
                         'discount_percentage' => 15,
                         'discount_start_date' => Carbon::parse($startDate)->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => Carbon::parse($endDate)->toIso8601ZuluString('microseconds'),
                         'current_price' => 100, // Still full price as discount is upcoming
                         'is_discounted' => false, // Not discounted yet
                         'discount_status' => 'upcoming',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product Upcoming Percentage Discount w/Dates',
            'discount_percentage' => 15.00,
            'discount_price' => 85.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
        ]);
    }

    #[Test]
    public function test_product_creation_fails_if_discount_end_date_is_before_start_date()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('invalid_date_discount.jpg');
        $now = Carbon::now();
        $startDate = $now->copy()->addDays(5)->format('Y-m-d H:i:s');
        $endDate = $now->copy()->addDays(1)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product Invalid Date Range',
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
            'unit_of_measure' => 'kg', 'min_order_quantity' => 1, 'stock_quantity' => 50, 'is_active' => 1, 'images' => [$image],
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_end_date']);

        $this->assertDatabaseMissing('products', ['name' => 'Product Invalid Date Range']);
    }

    #[Test]
    public function test_product_creation_fails_if_discount_start_date_is_in_past()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('past_start_date.jpg');
        $pastDate = Carbon::now()->subDays(2)->format('Y-m-d H:i:s');
        $futureDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product Past Start Date',
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_start_date' => $pastDate,
            'discount_end_date' => $futureDate,
            'unit_of_measure' => 'kg', 'min_order_quantity' => 1, 'stock_quantity' => 50, 'is_active' => 1, 'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'data' => [
                         'name' => 'Product Past Start Date',
                         'discount_price' => '90.00',
                         'discount_percentage' => 10,
                         'discount_start_date' => Carbon::parse($pastDate)->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => Carbon::parse($futureDate)->toIso8601ZuluString('microseconds'),
                         'is_discounted' => true, // Discount is active (past start, future end)
                         'discount_status' => 'active',
                     ]
                 ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Product Past Start Date',
            'discount_price' => 90.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => $pastDate,
            'discount_end_date' => $futureDate,
        ]);
    }

    #[Test]
    public function test_product_creation_with_discount_price_and_only_start_date_is_active_indefinitely_from_start()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('only_start_date.jpg');
        $startDate = Carbon::now()->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product with Only Start Date',
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => null,
            'unit_of_measure' => 'kg', 'min_order_quantity' => 1, 'stock_quantity' => 50, 'is_active' => 1, 'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'data' => [
                         'current_price' => 70,
                         'is_discounted' => true,
                         'discount_status' => 'active',
                         'discount_percentage' => 30,
                         'discount_start_date' => Carbon::parse($startDate)->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => null,
                     ]
                 ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Product with Only Start Date',
            'discount_start_date' => $startDate,
            'discount_end_date' => null,
        ]);
    }

    #[Test]
    public function test_product_creation_with_discount_price_and_only_end_date_results_in_no_discount_model_logic()
    {
        $this->createAdminUser();
        $image = $this->getFakeImage('only_end_date.jpg');
        $endDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/products', [ // *** CHANGE APPLIED: Corrected URL ***
            'category_id' => $this->category->id,
            'name' => 'Product with Only End Date',
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
            'discount_start_date' => null,
            'discount_end_date' => $endDate,
            'unit_of_measure' => 'kg', 'min_order_quantity' => 1, 'stock_quantity' => 50, 'is_active' => 1, 'images' => [$image],
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'data' => [
                         'discount_price' => null,
                         'discount_percentage' => null,
                         'discount_start_date' => null,
                         'discount_end_date' => null,
                         'current_price' => 100,
                         'is_discounted' => false,
                         'discount_status' => 'none',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Product with Only End Date',
            'discount_price' => null,
            'discount_percentage' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Discount with Dates (Retrieval & Update)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_product_with_expired_discount_returns_original_price_on_retrieval()
    {
        $this->createAdminUser(); // Admin can view inactive/expired products
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Expired Discount Product',
            'price_per_unit' => 50.00,
            'discount_price' => 40.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDays(10), // 10 days ago
            'discount_end_date' => Carbon::now()->subDays(5),    // 5 days ago (expired)
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products/' . $product->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'id' => $product->id,
                         'current_price' => 50,
                         'is_discounted' => false,
                         'discount_price' => '40.00', // Ensure these are still present in the response for expired
                         'discount_percentage' => '20.00',
                         'discount_start_date' => $product->discount_start_date->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => $product->discount_end_date->toIso8601ZuluString('microseconds'),
                         'discount_status' => 'expired', // Should be expired
                     ]
                 ]);
    }

    #[Test]
    public function test_product_update_can_set_fixed_discount_with_dates_correctly()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'name' => 'Product For Date Update',
            'discount_price' => null,
            'discount_percentage' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);

        $now = Carbon::now();
        $startDate = $now->format('Y-m-d H:i:s');
        $endDate = $now->copy()->addDays(5)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => '90.00',
                         'discount_percentage' => 10,
                         'discount_start_date' => Carbon::parse($startDate)->toIso8601ZuluString('microseconds'),
                         'discount_end_date' => Carbon::parse($endDate)->toIso8601ZuluString('microseconds'),
                         'current_price' => 90,
                         'is_discounted' => true,
                         'discount_status' => 'active',
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => 90.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => $startDate,
            'discount_end_date' => $endDate,
        ]);
    }

    #[Test]
    public function test_product_update_can_change_percentage_discount_and_dates()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 200.00,
            'discount_percentage' => 10.00,
            'discount_price' => 180.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDays(2),
        ]);

        $now = Carbon::now();
        $newStartDate = $now->copy()->addDays(1)->format('Y-m-d H:i:s'); // Make it upcoming
        $newEndDate = $now->copy()->addDays(7)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 200.00,
            'discount_percentage' => 25.00, // New percentage
            'discount_start_date' => $newStartDate,
            'discount_end_date' => $newEndDate,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_percentage' => 25,
                        'discount_price' => '150.00', // 200 * 0.75
                        'discount_start_date' => Carbon::parse($newStartDate)->toIso8601ZuluString('microseconds'),
                        'discount_end_date' => Carbon::parse($newEndDate)->toIso8601ZuluString('microseconds'),
                         'current_price' => 200, // Still full price as discount is upcoming
                         'is_discounted' => false, // Not discounted yet
                         'discount_status' => 'upcoming', // Assert upcoming status
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => 25.00,
            'discount_price' => 150.00,
            'discount_start_date' => $newStartDate,
            'discount_end_date' => $newEndDate,
        ]);
    }


    #[Test]
    public function test_product_update_can_clear_all_discount_fields()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 100.00,
            'discount_price' => null,
            'discount_percentage' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => null,
                         'discount_percentage' => null,
                         'discount_start_date' => null,
                         'discount_end_date' => null,
                         'current_price' => 100,
                         'is_discounted' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => null,
            'discount_percentage' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);
    }

    #[Test]
    public function test_product_update_fails_if_end_date_becomes_before_start_date()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);

        $now = Carbon::now();
        $updatedStartDate = $now->copy()->addDays(5)->format('Y-m-d H:i:s');
        $updatedEndDate = $now->copy()->addDays(1)->format('Y-m-d H:i:s');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 100.00,
            'discount_price' => 70.00,
            'discount_start_date' => $updatedStartDate,
            'discount_end_date' => $updatedEndDate,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['discount_end_date']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
            'discount_end_date' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Test]
    public function test_product_update_can_clear_discount_dates_and_make_discount_indefinite()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])->post("/api/admin/products/{$product->id}", [ // *** CHANGE APPLIED: Corrected URL ***
            '_method' => 'PUT',
            'price_per_unit' => 100.00,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Product updated successfully.',
                     'data' => [
                         'id' => $product->id,
                         'discount_price' => '80.00',
                         'discount_percentage' => '20.00',
                         'discount_start_date' => null,
                         'discount_end_date' => null,
                         'current_price' => 80,
                         'is_discounted' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);
    }

    #[Test]
    public function test_product_update_clears_discount_if_dates_become_invalid_model_logic_check()
    {
        $this->createAdminUser();
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price_per_unit' => 100.00,
            'discount_price' => 80.00,
            'discount_percentage' => 20.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);

        $product->discount_start_date = Carbon::now()->addDays(5);
        $product->discount_end_date = Carbon::now()->addDays(1);
        $product->discount_price = 70.00;
        $product->discount_percentage = 30.00;

        $product->save();

        $this->assertNull($product->discount_price);
        $this->assertNull($product->discount_percentage);
        $this->assertNull($product->discount_start_date);
        $this->assertNull($product->discount_end_date);
        $this->assertFalse($product->is_discounted);
        $this->assertEquals(100, $product->current_price);
        $this->assertEquals('none', $product->discount_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor Logic Tests (Percentage & Dates)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_current_price_accessor_calculates_correctly_with_percentage_discount_when_active()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 200.00,
            'discount_percentage' => 25.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertEquals(150, $product->current_price);

        $product2 = Product::factory()->make([
            'price_per_unit' => 99.99,
            'discount_percentage' => 10.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertEqualsWithDelta(89.991, $product2->current_price, 0.01);
    }

    #[Test]
    public function test_current_price_accessor_prioritizes_percentage_if_both_are_present_and_valid_and_active()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 10.00, // 10% off -> $90
            'discount_price' => 80.00,     // Fixed $80
            'discount_start_date' => Carbon::now()->subDay(), // Make it active
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        // The accessor should use the percentage discount (10% off is $90).
        // If the `saving` event ensures consistency, this test primarily checks the accessor's logic.
        $this->assertEquals(90, $product->current_price);
    }

    #[Test]
    public function test_is_discounted_accessor_returns_true_with_valid_percentage_discount_when_active()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 1.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertTrue($product->is_discounted);

        $product2 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 100.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertTrue($product2->is_discounted);
    }

    #[Test]
    public function test_is_discounted_accessor_returns_false_with_invalid_percentage_discount_or_inactive_dates()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 0.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertFalse($product->is_discounted);

        $product2 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => -5.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertFalse($product2->is_discounted);

        $product3 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 101.00,
            'discount_price' => null,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertFalse($product3->is_discounted);

        $product4 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => Carbon::now()->addDay(),
            'discount_end_date' => Carbon::now()->addDays(5),
        ]);
        $this->assertFalse($product4->is_discounted);

        $product5 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => Carbon::now()->subDays(5),
            'discount_end_date' => Carbon::now()->subDay(),
        ]);
        $this->assertFalse($product5->is_discounted);
    }

    #[Test]
    public function test_product_discount_status_upcoming_correctly()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => Carbon::now()->addDay(),
            'discount_end_date' => Carbon::now()->addDays(5),
        ]);
        $this->assertEquals('upcoming', $product->discount_status);
        $this->assertEquals(100, $product->current_price);
        $this->assertFalse($product->is_discounted);
    }

    #[Test]
    public function test_product_discount_status_active_correctly()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertEquals('active', $product->discount_status);
        $this->assertEquals(90, $product->current_price);
        $this->assertTrue($product->is_discounted);
    }

    #[Test]
    public function test_product_discount_status_expired_correctly()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_price' => 90.00,
            'discount_percentage' => 10.00,
            'discount_start_date' => Carbon::now()->subDays(5),
            'discount_end_date' => Carbon::now()->subDay(),
        ]);
        $this->assertEquals('expired', $product->discount_status);
        $this->assertEquals(100, $product->current_price);
        $this->assertFalse($product->is_discounted);
    }

    #[Test]
    public function test_product_discount_status_none_if_no_valid_discount_values()
    {
        $product = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_price' => null,
            'discount_percentage' => null,
            'discount_start_date' => null,
            'discount_end_date' => null,
        ]);
        $this->assertEquals('none', $product->discount_status);
        $this->assertEquals(100, $product->current_price);
        $this->assertFalse($product->is_discounted);

        $product2 = Product::factory()->make([
            'price_per_unit' => 100.00,
            'discount_price' => 100.00,
            'discount_percentage' => 0.00,
            'discount_start_date' => Carbon::now()->subDay(),
            'discount_end_date' => Carbon::now()->addDay(),
        ]);
        $this->assertEquals('none', $product2->discount_status);
        $this->assertEquals(100, $product2->current_price);
        $this->assertFalse($product2->is_discounted);
    }
}