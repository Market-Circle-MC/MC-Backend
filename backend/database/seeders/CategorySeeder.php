<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Parent Categories ---
        $freshProducts = Category::firstOrCreate(['slug' => Str::slug('Fresh Products')], [
            'name' => 'Fresh Products',
            'description' => 'Direct from farm to table.',
            'image_url' => 'https://images.pexels.com/photos/2255935/pexels-photo-2255935.jpeg?auto=compress&cs=tinysrgb&w=600',
            'is_active' => true,
        ]);

        $dairyBreakfast = Category::firstOrCreate(['slug' => Str::slug('Dairy & Breakfast')], [
            'name' => 'Dairy & Breakfast',
            'description' => 'Start your day right with our selection.',
            'image_url' => 'https://samsungfood.com/wp-content/uploads/2023/02/shutterstock_1679020255.jpg',
            'is_active' => true,
        ]);

        $animalProteins = Category::firstOrCreate(['slug' => Str::slug('Animal Proteins')], [
            'name' => 'Animal Proteins',
            'description' => 'High-quality meats and seafood.',
            'image_url' => 'https://nutritionfacts.org/app/uploads/2017/05/Animal-Protein.jpeg',
            'is_active' => true,
        ]);

        $pantryStaples = Category::firstOrCreate(['slug' => Str::slug('Pantry Staples')], [
            'name' => 'Pantry Staples',
            'description' => 'Everyday essentials for your kitchen.',
            'image_url' => 'https://www.paknsave.co.nz/-/media/Project/Sitecore/Brands/Brand-PAKnSAVE/Articles/Pantry-Staples/Pantry-Staples-v2-800pxiStock-1212928413.jpg?h=308&w=700&hash=F2BF849E7DADDB3ECB36E6F3205DAE34',
            'is_active' => true,
        ]);

        // --- Child Categories (linking to parents) ---
        Category::firstOrCreate(['slug' => Str::slug('Organic Vegetables')], [
            'name' => 'Organic Vegetables',
            'description' => 'Naturally grown, fresh vegetables.',
            'parent_id' => $freshProducts->id,
            'image_url' => 'https://www.thegardener.co.za/wp-content/uploads/2018/05/Fotolia_93014626_Subscription_Monthly_M.jpg',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Fresh Fruits')], [
            'name' => 'Fresh Fruits',
            'description' => 'Juicy and seasonal fruits.',
            'parent_id' => $freshProducts->id,
            'image_url' => 'https://www.unlockfood.ca/EatRightOntario/media/Website-images-resized/How-to-store-fruit-to-keep-it-fresh-resized.jpg',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Milk & Cheese')], [
            'name' => 'Milk & Cheese',
            'description' => 'A wide range of dairy products.',
            'parent_id' => $dairyBreakfast->id,
            'image_url' => 'https://t3.ftcdn.net/jpg/01/90/89/94/240_F_190899430_XPVcb9DcoLPaSyFPrCWgwxnJEAzPTH7q.jpg',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Cereal & Grains')], [
            'name' => 'Cereal & Grains',
            'description' => 'Healthy options for a great start.',
            'parent_id' => $dairyBreakfast->id, // Consider if some cereals should be under Pantry Staples
            'image_url' => 'https://media.newyorker.com/photos/59095e38019dfc3494e9fa85/master/pass/Vara-Ancient-Grains-primary-2.jpg',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Red Meat')], [
            'name' => 'Red Meat',
            'description' => 'Premium cuts of beef, lamb, and pork.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://merriam-webster.com/assets/mw/images/article/art-wap-article-main/red-meat-2115-66f9187380c90a8e5e5d97ed45f40e98@1x.jpg',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Poultry')], [
            'name' => 'Poultry',
            'description' => 'Fresh chicken, turkey, and duck.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://www.alltech.com/sites/default/files/meat-quality-internal-web.png',
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Seafood')], [
            'name' => 'Seafood',
            'description' => 'Freshly caught fish and shellfish.',
            'parent_id' => $animalProteins->id,
            'image_url' => 'https://domf5oio6qrcr.cloudfront.net/medialibrary/16013/p1-seafoodcollage-hh0125-gi1185677996.jpg',
            'is_active' => true,
        ]);


        Category::firstOrCreate(['slug' => Str::slug('Local Grains & Flours')], [
            'name' => 'Local Grains & Flours',
            'description' => 'Essential grains and flours for Ghanaian dishes like Banku, Kenkey, and Fufu.',
            'parent_id' => $pantryStaples->id,
            'image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRmATUaEffxGioNN-zv_Ob1wWzgE5mHOrpydw&s', // Placeholder image
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Oils & Condiments')], [
            'name' => 'Oils & Condiments',
            'description' => 'Cooking oils, sauces, and seasonings for authentic Ghanaian flavors.',
            'parent_id' => $pantryStaples->id,
            'image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTST9M5E-V3-VIsSAZVeKnAkevViUoMYCp7iQ&s', // Placeholder image
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Dried & Processed Foods')], [
            'name' => 'Dried & Processed Foods',
            'description' => 'Convenient dried goods and processed items for your pantry.',
            'parent_id' => $pantryStaples->id,
            'image_url' => 'https://i.guim.co.uk/img/media/da289232c46fcf097ced4253b9ffbe4b8dd639aa/0_0_6704_4916/master/6704.jpg?width=700&quality=85&auto=format&fit=max&s=ea1252ff40ed2cbed1eaede8c010e11f', // Placeholder image
            'is_active' => true,
        ]);

        Category::firstOrCreate(['slug' => Str::slug('Spices & Seasonings')], [
            'name' => 'Spices & Seasonings',
            'description' => 'Aromatic spices and blends to enhance your cooking.',
            'parent_id' => $pantryStaples->id,
            'image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQHp9yWhYAJgh6aBg4NuoHT_DQxmzJN1RMfJA&s', // Placeholder image
            'is_active' => true,
        ]);

    }
}