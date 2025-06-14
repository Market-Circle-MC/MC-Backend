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
use Illuminate\Support\Str;;

class ProductApiTest extends TestCase
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
    protected function setUp(): void
    {
        parent::setUp();
        // Use a real disk for testing image storage if you want to verify actual files
        // Or keep fake for speed. For this scenario, `Storage::fake()` is appropriate.
        Storage::fake('public');
    }

    // Helper to create an admin user and authenticate
    protected function createAdminUser()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);
        return $admin;
    }

    // Helper to create a customer user and authenticate
    protected function createCustomerUser()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer, ['*']);
        return $customer;
    }

    /**
     * Test a guest can view all active products.
     */
    public function test_guest_can_view_all_products()
    {
        Category::factory()->create(['id' => 1, 'is_active' => true]); // Ensure category exists
        Product::factory(5)->create(['is_active' => true, 'category_id' => 1]);
        Product::factory(2)->create(['is_active' => false, 'category_id' => 1]); // Inactive products

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data.data') // 'data.data' for paginated response
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'data' => [
                             '*' => ['id', 'category_id', 'name', 'slug', 'price_per_unit', 'stock_quantity', 'is_active']
                         ],
                         'total', 'per_page', 'current_page' // Pagination keys
                     ]
                 ]);
    }

    /**
     * Test an authenticated admin can create a product with an image.
     */
    public function test_admin_can_create_product_with_image()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        // FIX for GD: Use create() instead of image()
        $image = UploadedFile::fake()->create('product_image.jpg', 100, 'image/jpeg'); // Name, size in KB, MIME type

        $productData = [
            'category_id' => $category->id,
            'name' => 'Test Product Image',
            'price_per_unit' => 19.99,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1,
            'stock_quantity' => 100,
            'images' => [$image], // Pass file in an array
        ];

        // For file uploads, use post (multipart/form-data) instead of postJson
        $response = $this->post('/api/products', $productData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Product created successfully.',
                     'data' => [
                         'name' => 'Test Product Image',
                         'category_id' => $category->id,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'id', 'name', 'slug', 'price_per_unit', 'stock_quantity',
                         'images' => [ // Assert image structure
                             '*' => ['id', 'product_id', 'image_url', 'is_main_image']
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product Image',
            'category_id' => $category->id,
        ]);

        $product = Product::where('name', 'Test Product Image')->first();
        $this->assertCount(1, $product->images);
        // Correct way to get the path from Storage::url() for assertion
        $storedPath = str_replace(Storage::url(''), '', $product->images->first()->image_url);
        Storage::disk('public')->assertExists($storedPath);
    }


    /**
     * Test an authenticated admin can create a product without an image.
     */
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

        $response = $this->postJson('/api/products', $productData); // Use postJson as no file upload

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
        $this->assertCount(0, $product->images); // No images created
    }

    /**
     * Test customer cannot create a product.
     */
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

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(403) // Forbidden
                 ->assertJson(['message' => 'Unauthorized. Admin access required.']);

        $this->assertDatabaseMissing('products', ['name' => 'Forbidden Product']);
    }

    /**
     * Test unauthenticated user cannot create a product.
     */
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

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseMissing('products', ['name' => 'Unauthorized Product']);
    }

    /**
     * Test product creation with missing required fields.
     */
    public function test_product_creation_fails_with_missing_fields()
    {
        $this->createAdminUser();
        $response = $this->postJson('/api/products', []); // Empty data

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id', 'name', 'price_per_unit', 'unit_of_measure', 'min_order_quantity', 'stock_quantity']);
    }

    /**
     * Test product creation with invalid category_id.
     */
    public function test_product_creation_fails_with_invalid_category_id()
    {
        $this->createAdminUser();
        $productData = [
            'category_id' => 9999, // Non-existent category
            'name' => 'Invalid Category Product',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];
        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * Test product creation with inactive category_id.
     */
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
        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * Test product creation with non-unique name.
     */
    public function test_product_creation_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['name' => 'Existing Product Name', 'category_id' => $category->id]);

        $productData = [
            'category_id' => $category->id,
            'name' => 'Existing Product Name', // Duplicate name
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'unit',
            'min_order_quantity' => 1,
            'stock_quantity' => 10,
        ];
        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test product creation with non-unique SKU.
     */
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
            'sku' => 'UNIQUE-SKU-123', // Duplicate SKU
        ];
        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['sku']);
    }

    /**
     * Test guest can retrieve a single product.
     */
    public function test_guest_can_retrieve_single_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        // Create a dummy image file on the fake storage for this test to display
        $fakeImageName = 'products/' . Str::random(40) . '.jpg';
        Storage::disk('public')->put($fakeImageName, 'dummy content'); // Ensure file exists for image_url
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => Storage::url($fakeImageName), // Make sure image_url is consistently stored
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
                     'data' => [
                         'id', 'name', 'slug', 'category_id', 'price_per_unit', 'stock_quantity',
                         'category' => ['id', 'name'], // Eager loaded category
                         'images' => [ // Eager loaded images
                             '*' => ['id', 'product_id', 'image_url']
                         ]
                     ]
                 ]);
    }

    /**
     * Test retrieving a non-existent product returns 404.
     */
    public function test_retrieving_non_existent_product_returns_404()
    {
        $response = $this->getJson('/api/products/99999'); // Non-existent ID
        $response->assertStatus(404);
    }

    /**
     * Test admin can update a product.
     */
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

        $response = $this->putJson('/api/products/' . $product->id, $updateData);

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

    /**
     * Test admin can partially update a product (e.g., only stock quantity).
     */
    public function test_admin_can_partially_update_product()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 50, 'is_active' => true]);

        $updateData = [
            'stock_quantity' => 45,
            'is_active' => false,
        ];

        $response = $this->patchJson('/api/products/' . $product->id, $updateData);

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

    /**
     * Test admin can update a product with new images.
     */
    public function test_admin_can_update_product_with_new_images()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        // FIX for GD: Use create() instead of image()
        $image1 = UploadedFile::fake()->create('updated_image1.png', 100, 'image/png');
        $image2 = UploadedFile::fake()->create('updated_image2.gif', 100, 'image/gif');

        $updateData = [
            'images' => [$image1, $image2],
        ];

        // Use post for file uploads, even for PUT/PATCH with Laravel's testing helpers
        $response = $this->post('/api/products/' . $product->id, array_merge($updateData, ['_method' => 'PUT']));

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
                             '*' => ['id', 'product_id', 'image_url']
                         ]
                     ]
                 ]);

        $product->refresh(); // Reload product to get updated images relationship
        $this->assertCount(2, $product->images); // Expect 2 new images
        Storage::disk('public')->assertExists(str_replace(Storage::url(''), '', $product->images[0]->image_url));
        Storage::disk('public')->assertExists(str_replace(Storage::url(''), '', $product->images[1]->image_url));
    }


    /**
     * Test customer cannot update a product.
     */
    public function test_customer_cannot_update_product()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $updateData = ['name' => 'Attempted Update'];

        $response = $this->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(403) // Forbidden
                 ->assertJson(['message' => 'Unauthorized. Admin access required.']);

        $this->assertDatabaseMissing('products', ['name' => 'Attempted Update']);
    }

    /**
     * Test unauthenticated user cannot update a product.
     */
    public function test_unauthenticated_cannot_update_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $updateData = ['name' => 'Unauthorized Update'];

        $response = $this->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertDatabaseMissing('products', ['name' => 'Unauthorized Update']);
    }

    /**
     * Test product update fails with invalid category_id.
     */
    public function test_product_update_fails_with_invalid_category_id()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $updateData = ['category_id' => 9999]; // Non-existent ID
        $response = $this->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * Test product update fails with inactive category_id.
     */
    public function test_product_update_fails_with_inactive_category_id()
    {
        $this->createAdminUser();
        $activeCategory = Category::factory()->create(['is_active' => true]);
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $activeCategory->id]);

        $updateData = ['category_id' => $inactiveCategory->id]; // Inactive category
        $response = $this->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * Test product update fails with non-unique name.
     */
    public function test_product_update_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        Product::factory()->create(['name' => 'Existing Product', 'category_id' => $category->id]);
        $productToUpdate = Product::factory()->create(['name' => 'Another Product', 'category_id' => $category->id]);

        $updateData = ['name' => 'Existing Product']; // Duplicate name
        $response = $this->putJson('/api/products/' . $productToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test admin can delete a product.
     */
    public function test_admin_can_delete_product()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        
        // Create a dummy image file on the fake storage for this test to delete
        $fakeImageName = 'products/' . Str::random(40) . '.jpg'; // Generate a random file name
        Storage::disk('public')->put($fakeImageName, 'dummy image content'); // Put actual content
        
        // Create the ProductImage model record pointing to this fake file
        $image1 = ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_url' => Storage::url($fakeImageName), // Ensure this URL matches the path
        ]);

        // Re-assign $fakeImageName to be the full relative path used by assertMissing
        $relativePathToDelete = str_replace(Storage::url(''), '', $image1->image_url);

        $response = $this->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(200) // Or 204 No Content
                 ->assertJson([
                     'message' => 'Product deleted successfully.',
                 ]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing($relativePathToDelete); // Assert the file is deleted
    }

    /**
     * Test customer cannot delete a product.
     */
    public function test_customer_cannot_delete_product()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(403) // Forbidden
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /**
     * Test unauthenticated user cannot delete a product.
     */
    public function test_unauthenticated_cannot_delete_product()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /**
     * Test deleting a non-existent product returns 404.
     */
    public function test_deleting_non_existent_product_returns_404()
    {
        $this->createAdminUser();
        $response = $this->deleteJson('/api/products/99999'); // Non-existent ID
        $response->assertStatus(404);
    }
}
