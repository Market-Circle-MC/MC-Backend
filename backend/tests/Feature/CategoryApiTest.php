<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema; // To check if columns exist

class CategoryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    // Helper to create an admin user and act as them
    protected function createAdminUser()
    {
        // Assuming your User model has a 'role' column
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');
        return $admin;
    }

    // Helper to create a customer user and act as them
    protected function createCustomerUser()
    {
        // Assuming your User model has a 'role' column
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer, 'sanctum');
        return $customer;
    }

    // Determine if nested set columns (lft, rgt, depth) should be expected in JSON
    protected function expectedCategoryJsonStructure($includeRelationships = false)
    {
        $baseStructure = [
            'id', 'name', 'slug', 'description', 'parent_id', 'image_url', 'is_active',
            'created_at', 'updated_at',
        ];

        // Only include nested set columns if they are not hidden AND are actually in the table schema
        if (Schema::hasColumns('categories', ['lft', 'rgt', 'depth']) &&
            !in_array('lft', (new Category())->getHidden() ?? []) // Check if not hidden
        ) {
             $baseStructure = array_merge($baseStructure, ['lft', 'rgt', 'depth']);
        }

        return $baseStructure;
    }


    #[Test]
    public function example()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    #[Test]
    public function guest_can_view_all_categories()
    {
        Category::factory()->count(5)->create(['is_active' => true]);
        Category::factory()->count(2)->create(['is_active' => false]); // Inactive categories

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data') // Only active categories should be returned for guests/customers
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => $this->expectedCategoryJsonStructure(true) // Expecting parent/children keys
                     ]
                 ]);
    }

    #[Test]
    public function customer_can_view_all_categories()
    {
        $this->createCustomerUser();
        Category::factory()->count(5)->create(['is_active' => true]);
        Category::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data')
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => $this->expectedCategoryJsonStructure(true)
                     ]
                 ]);
    }

    #[Test]
    public function admin_can_view_all_categories()
    {
        $this->createAdminUser();
        Category::factory()->count(5)->create(['is_active' => true]);
        Category::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(7, 'data') // Admin should see all categories, active or not
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => $this->expectedCategoryJsonStructure(true)
                     ]
                 ]);
    }

    #[Test]
    public function admin_can_create_category()
    {
        $this->createAdminUser();

        $categoryData = [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'Description for new category.',
            'image_url' => 'http://example.com/new-cat-image.jpg', // Added image_url
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Category created successfully.',
                     'data' => [
                         'name' => 'New Category',
                         'slug' => 'new-category',
                         'image_url' => 'http://example.com/new-cat-image.jpg', // Assert image_url
                         'is_active' => true,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => $this->expectedCategoryJsonStructure() // No relationships for creation response
                 ]);

        $this->assertDatabaseHas('categories', ['name' => 'New Category']);
    }

    #[Test]
    public function customer_cannot_create_category()
    {
        $this->createCustomerUser();

        $categoryData = [
            'name' => 'Customer Category',
            'slug' => 'customer-category',
            'description' => 'Description.',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(403) // Forbidden due to authorization in StoreCategoryRequest
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);

        $this->assertDatabaseMissing('categories', ['name' => 'Customer Category']);
    }

    #[Test]
    public function unauthenticated_cannot_create_category()
    {
        $categoryData = [
            'name' => 'Unauthorized Category',
            'slug' => 'unauthorized-category',
            'description' => 'Description.',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(401) // Unauthorized (no token)
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);

        $this->assertDatabaseMissing('categories', ['name' => 'Unauthorized Category']);
    }

    #[Test]
    public function category_creation_fails_with_missing_name()
    {
        $this->createAdminUser();

        $categoryData = [
            // 'name' is missing
            'slug' => 'missing-name',
            'description' => 'Description.',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(422) // Unprocessable Entity (Validation)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function category_creation_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        Category::factory()->create(['name' => 'Existing Category']);

        $categoryData = [
            'name' => 'Existing Category', // Duplicate name
            'slug' => 'existing-category-2',
            'description' => 'Description.',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function category_creation_fails_with_invalid_parent_id()
    {
        $this->createAdminUser();

        $categoryData = [
            'name' => 'Invalid Parent Category',
            'slug' => 'invalid-parent-category',
            'description' => 'Description.',
            'parent_id' => 99999, // Non-existent parent ID
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function category_creation_fails_with_inactive_parent_id()
    {
        $this->createAdminUser();
        $inactiveParent = Category::factory()->create(['is_active' => false]);

        $categoryData = [
            'name' => 'Child of Inactive Parent',
            'slug' => 'child-of-inactive-parent',
            'description' => 'Description.',
            'parent_id' => $inactiveParent->id,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function category_creation_with_valid_parent_id()
    {
        $this->createAdminUser();
        $parentCategory = Category::factory()->create(['is_active' => true]);

        $categoryData = [
            'name' => 'Child Category',
            'slug' => 'child-category',
            'description' => 'Description for child category.',
            'parent_id' => $parentCategory->id,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Category created successfully.',
                     'data' => [
                         'name' => 'Child Category',
                         'parent_id' => $parentCategory->id,
                     ]
                 ]);

        $this->assertDatabaseHas('categories', ['name' => 'Child Category', 'parent_id' => $parentCategory->id]);
    }

    #[Test]
    public function guest_can_retrieve_single_category()
    {
        // Create a category with a parent and a child to fully test relationships
        $parentCategory = Category::factory()->create(['is_active' => true, 'name' => 'Parent Category']);
        $category = Category::factory()->create(['is_active' => true, 'parent_id' => $parentCategory->id, 'name' => 'Main Category']);
        Category::factory()->create(['is_active' => true, 'parent_id' => $category->id, 'name' => 'Child Category']);

        $response = $this->getJson('/api/categories/' . $category->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category retrieved successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => 'Main Category',
                         'parent_id' => $parentCategory->id,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => array_merge(
                         $this->expectedCategoryJsonStructure(),
                     )
                 ]);
    }

    #[Test]
    public function retrieving_non_existent_category_returns_404()
    {
        $response = $this->getJson('/api/categories/99999'); // Non-existent ID

        $response->assertStatus(404);
    }

    #[Test]
    public function admin_can_update_category()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Updated Category Name',
            'description' => 'Updated description.',
            'is_active' => false,
        ];

        $response = $this->putJson('/api/admin/categories/' . $category->id, $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category updated successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => 'Updated Category Name',
                         'is_active' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category Name', 'is_active' => false]);
    }

    #[Test]
    public function admin_can_partially_update_category()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original description.',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'Partially Updated Name', // Only update name
        ];

        $response = $this->patchJson('/api/admin/categories/' . $category->id, $updateData);

        $response->assertStatus(200) // This is where it failed previously due to 405
                 ->assertJson([
                     'message' => 'Category updated successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => 'Partially Updated Name',
                         'description' => 'Original description.', // Should remain unchanged
                         'is_active' => true, // Should remain unchanged
                     ]
                 ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Partially Updated Name',
            'description' => 'Original description.',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function customer_cannot_update_category()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Attempted Update',
        ];

        $response = $this->putJson('/api/admin/categories/' . $category->id, $updateData);

        $response->assertStatus(403) // Forbidden
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);
        $this->assertDatabaseMissing('categories', ['name' => 'Attempted Update']);
    }

    #[Test]
    public function unauthenticated_cannot_update_category()
    {
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Unauthenticated Update',
        ];

        $response = $this->putJson('/api/admin/categories/' . $category->id, $updateData);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]); // Ensure it's not deleted
    }

    #[Test]
    public function category_update_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        Category::factory()->create(['name' => 'Existing Category']);
        $categoryToUpdate = Category::factory()->create(['name' => 'Category To Update']);

        $updateData = [
            'name' => 'Existing Category', // Duplicate name
        ];

        $response = $this->putJson('/api/admin/categories/' . $categoryToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function category_update_fails_with_invalid_parent_id()
    {
        $this->createAdminUser();
        $categoryToUpdate = Category::factory()->create();

        $updateData = [
            'parent_id' => 99999, // Non-existent parent ID
        ];

        $response = $this->putJson('/api/admin/categories/' . $categoryToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function category_update_fails_if_parent_is_self()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();

        $updateData = [
            'parent_id' => $category->id, // Cannot be its own parent
        ];

        $response = $this->putJson('/api/admin/categories/' . $category->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function admin_can_delete_category()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/admin/categories/' . $category->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category deleted successfully.',
                 ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[Test]
    public function customer_cannot_delete_category()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/admin/categories/' . $category->id);

        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    #[Test]
    public function unauthenticated_cannot_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/admin/categories/' . $category->id);

        $response->assertStatus(401)
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    #[Test]
    public function deleting_non_existent_category_returns_404()
    {
        $this->createAdminUser();
        $response = $this->deleteJson('/api/admin/categories/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function cannot_delete_category_with_products()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]); // Create a product in this category

        $response = $this->deleteJson('/api/admin/categories/' . $category->id);

        $response->assertStatus(409)
                 ->assertJson([
                     'message' => 'Cannot delete category: It has associated products.',
                 ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    #[Test]
    public function cannot_delete_category_with_children()
    {
        $this->createAdminUser();
        $parentCategory = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parentCategory->id]); // Create a child category

        $response = $this->deleteJson('/api/admin/categories/' . $parentCategory->id);

        $response->assertStatus(409)
                 ->assertJson([
                     'message' => 'Cannot delete category: It has active child categories. Re-assign or delete children first.',
                 ]);
        $this->assertDatabaseHas('categories', ['id' => $parentCategory->id]);
    }
}