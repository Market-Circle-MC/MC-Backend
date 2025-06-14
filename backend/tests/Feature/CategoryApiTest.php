<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;


class CategoryApiTest extends TestCase
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

    // Helper to create an admin user and authenticate
    protected function createAdminUser()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']); // Grant all abilities to admin for testing
        return $admin;
    }

    // Helper to create a customer user and authenticate
    protected function createCustomerUser()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer, ['*']); // Grant all abilities
        return $customer;
    }

    /**
     * Test a guest can view all categories.
     * Index endpoint should be publicly accessible for active categories.
     */
    public function test_guest_can_view_all_categories()
    {
        Category::factory(5)->create(['is_active' => true]);
        Category::factory(2)->create(['is_active' => false]); // Inactive categories

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data') // Only active categories should be returned
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         '*' => ['id', 'name', 'slug', 'description', 'parent_id', 'image_url', 'is_active', 'created_at', 'updated_at']
                     ]
                 ]);
    }

    /**
     * Test an authenticated customer can view all categories.
     */
    public function test_customer_can_view_all_categories()
    {
        $this->createCustomerUser();
        Category::factory(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data');
    }

    /**
     * Test an authenticated admin can view all categories.
     */
    public function test_admin_can_view_all_categories()
    {
        $this->createAdminUser();
        Category::factory(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data');
    }

    /**
     * Test an authenticated admin can create a category.
     */
    public function test_admin_can_create_category()
    {
        $this->createAdminUser();
        $categoryData = [
            'name' => 'New Category',
            'description' => 'A description for the new category.',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Category created successfully.',
                     'data' => [
                         'name' => 'New Category',
                         'slug' => 'new-category',
                         'is_active' => true,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => ['id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at']
                 ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
            'is_active' => true,
        ]);
    }

    /**
     * Test an authenticated customer cannot create a category.
     */
    public function test_customer_cannot_create_category()
    {
        $this->createCustomerUser();
        $categoryData = [
            'name' => 'Customer Category',
            'description' => 'Should not be created.',
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(403) // Forbidden due to authorization in StoreCategoryRequest
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);

        $this->assertDatabaseMissing('categories', ['name' => 'Customer Category']);
    }

    /**
     * Test an unauthenticated user cannot create a category.
     */
    public function test_unauthenticated_cannot_create_category()
    {
        $categoryData = [
            'name' => 'Guest Category',
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(401) // Unauthorized (no token)
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);

        $this->assertDatabaseMissing('categories', ['name' => 'Guest Category']);
    }

    /**
     * Test category creation with missing required fields.
     */
    public function test_category_creation_fails_with_missing_name()
    {
        $this->createAdminUser();
        $categoryData = [
            'description' => 'Missing name.',
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(422) // Unprocessable Entity (Validation)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test category creation with non-unique name.
     */
    public function test_category_creation_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        Category::factory()->create(['name' => 'Existing Category']);

        $categoryData = [
            'name' => 'Existing Category',
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test category creation with invalid parent_id.
     */
    public function test_category_creation_fails_with_invalid_parent_id()
    {
        $this->createAdminUser();
        $categoryData = [
            'name' => 'Child Category',
            'parent_id' => 9999, // Non-existent ID
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    /**
     * Test category creation with an inactive parent_id.
     */
    public function test_category_creation_fails_with_inactive_parent_id()
    {
        $this->createAdminUser();
        $inactiveParent = Category::factory()->create(['is_active' => false]);

        $categoryData = [
            'name' => 'Child Category',
            'parent_id' => $inactiveParent->id,
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    /**
     * Test category creation with a valid parent_id.
     */
    public function test_category_creation_with_valid_parent_id()
    {
        $this->createAdminUser();
        $parentCategory = Category::factory()->create(['is_active' => true]);

        $categoryData = [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ];

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Category created successfully.',
                     'data' => [
                         'name' => 'Child Category',
                         'parent_id' => $parentCategory->id,
                     ]
                 ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ]);
    }

    /**
     * Test guest can retrieve a single category.
     */
    public function test_guest_can_retrieve_single_category()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $response = $this->getJson('/api/categories/' . $category->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category retrieved successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => $category->name,
                     ]
                 ])
                 ->assertJsonStructure([
                     'message',
                     'data' => ['id', 'name', 'slug', 'description', 'parent_id', 'image_url', 'is_active', 'created_at', 'updated_at', 'parent', 'children']
                 ]);
    }

    /**
     * Test retrieving a non-existent category returns 404.
     */
    public function test_retrieving_non_existent_category_returns_404()
    {
        $response = $this->getJson('/api/categories/99999'); // Non-existent ID

        $response->assertStatus(404); // Not Found
    }

    /**
     * Test admin can update a category.
     */
    public function test_admin_can_update_category()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['name' => 'Old Name', 'description' => 'Old Desc']);
        $newParent = Category::factory()->create(['is_active' => true]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'New Description',
            'parent_id' => $newParent->id,
            'is_active' => false,
        ];

        $response = $this->putJson('/api/categories/' . $category->id, $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category updated successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => 'Updated Name',
                         'slug' => 'updated-name', // Slug should be updated
                         'description' => 'New Description',
                         'parent_id' => $newParent->id,
                         'is_active' => false,
                     ]
                 ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'description' => 'New Description',
            'parent_id' => $newParent->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test admin can partially update a category (e.g., only description).
     */
    public function test_admin_can_partially_update_category()
    {
        $this->createAdminUser();
        $category = Category::factory()->create(['name' => 'Partial Update', 'description' => 'Original Desc']);

        $updateData = [
            'description' => 'Only description updated.',
        ];

        $response = $this->patchJson('/api/categories/' . $category->id, $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Category updated successfully.',
                     'data' => [
                         'id' => $category->id,
                         'name' => 'Partial Update', // Name should remain unchanged
                         'description' => 'Only description updated.',
                     ]
                 ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Partial Update',
            'description' => 'Only description updated.',
        ]);
    }


    /**
     * Test customer cannot update a category.
     */
    public function test_customer_cannot_update_category()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create();
        $updateData = [
            'name' => 'Attempted Update',
        ];

        $response = $this->putJson('/api/categories/' . $category->id, $updateData);

        $response->assertStatus(403) // Forbidden
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);
        $this->assertDatabaseMissing('categories', ['name' => 'Attempted Update']);
    }

    /**
     * Test unauthenticated user cannot update a category.
     */
    public function test_unauthenticated_cannot_update_category()
    {
        $category = Category::factory()->create();
        $updateData = [
            'name' => 'Unauthenticated Update',
        ];

        $response = $this->putJson('/api/categories/' . $category->id, $updateData);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);
        $this->assertDatabaseMissing('categories', ['name' => 'Unauthenticated Update']);
    }

    /**
     * Test category update fails with non-unique name.
     */
    public function test_category_update_fails_with_non_unique_name()
    {
        $this->createAdminUser();
        Category::factory()->create(['name' => 'Category A']);
        $categoryToUpdate = Category::factory()->create(['name' => 'Category B']);

        $updateData = [
            'name' => 'Category A', // Attempt to set name to an existing one
        ];

        $response = $this->putJson('/api/categories/' . $categoryToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test category update fails with invalid parent_id.
     */
    public function test_category_update_fails_with_invalid_parent_id()
    {
        $this->createAdminUser();
        $categoryToUpdate = Category::factory()->create();

        $updateData = [
            'parent_id' => 99999, // Non-existent ID
        ];

        $response = $this->putJson('/api/categories/' . $categoryToUpdate->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    /**
     * Test category update fails if parent_id is the category itself.
     */
    public function test_category_update_fails_if_parent_is_self()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();

        $updateData = [
            'parent_id' => $category->id, // Cannot be its own parent
        ];

        $response = $this->putJson('/api/categories/' . $category->id, $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['parent_id']);
    }

    /**
     * Test admin can delete a category.
     */
    public function test_admin_can_delete_category()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/categories/' . $category->id);

        $response->assertStatus(200) // Or 204 No Content
                 ->assertJson([
                     'message' => 'Category deleted successfully.',
                 ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * Test customer cannot delete a category.
     */
    public function test_customer_cannot_delete_category()
    {
        $this->createCustomerUser();
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/categories/' . $category->id);

        $response->assertStatus(403) // Forbidden
                 ->assertJson([
                     'message' => 'Unauthorized. Admin access required.'
                 ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /**
     * Test unauthenticated user cannot delete a category.
     */
    public function test_unauthenticated_cannot_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/categories/' . $category->id);

        $response->assertStatus(401) // Unauthorized
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /**
     * Test deleting a non-existent category returns 404.
     */
    public function test_deleting_non_existent_category_returns_404()
    {
        $this->createAdminUser();
        $response = $this->deleteJson('/api/categories/99999'); // Non-existent ID

        $response->assertStatus(404); // Not Found
    }

    /**
     * Test cannot delete category if it has associated products.
     */
    public function test_cannot_delete_category_with_products()
    {
        $this->createAdminUser();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]); // Create a product in this category

        $response = $this->deleteJson('/api/categories/' . $category->id);

        $response->assertStatus(409) // Conflict
                 ->assertJson([
                     'message' => 'Cannot delete category: It has associated products.',
                 ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]); // Category should still exist
    }

    /**
     * Test cannot delete category if it has child categories.
     */
    public function test_cannot_delete_category_with_children()
    {
        $this->createAdminUser();
        $parentCategory = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parentCategory->id]); // Create a child category

        $response = $this->deleteJson('/api/categories/' . $parentCategory->id);

        $response->assertStatus(409) // Conflict
                 ->assertJson([
                     'message' => 'Cannot delete category: It has active child categories. Re-assign or delete children first.',
                 ]);

        $this->assertDatabaseHas('categories', ['id' => $parentCategory->id]);
    }
}
