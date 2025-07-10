<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch existing categories for linking
        $organicVegetables = Category::where('slug', Str::slug('Organic Vegetables'))->firstOrFail();
        $milkAndCheese = Category::where('slug', Str::slug('Milk & Cheese'))->firstOrFail();
        $redMeat = Category::where('slug', Str::slug('Red Meat'))->firstOrFail();
        $freshFruits = Category::where('slug', Str::slug('Fresh Fruits'))->firstOrFail();
        $poultry = Category::where('slug', Str::slug('Poultry'))->firstOrFail();
        $cerealAndGrains = Category::where('slug', Str::slug('Cereal & Grains'))->firstOrFail();
        $seafood = Category::where('slug', Str::slug('Seafood'))->firstOrFail(); 
        $localGrainsAndFlours = Category::where('slug', Str::slug('Local Grains & Flours'))->firstOrFail();
        $oilsAndCondiments = Category::where('slug', Str::slug('Oils & Condiments'))->firstOrFail();
        $driedAndProcessedFoods = Category::where('slug', Str::slug('Dried & Processed Foods'))->firstOrFail();
        $spicesAndSeasonings = Category::where('slug', Str::slug('Spices & Seasonings'))->firstOrFail();


        // --- Products ---

        // Organic Vegetables
        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Spinach',
            'slug' => Str::slug('Organic Spinach'),
            'short_description' => 'Freshly harvested organic spinach.',
            'description' => 'Locally grown, pesticide-free organic spinach. Great for salads, smoothies, or sautéing.',
            'price_per_unit' => 3.99,
            'unit_of_measure' => 'bunch',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'OVSPIN001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.orgpick.com/cdn/shop/products/spnach_large_01cac1a1-246f-433c-b02b-e2c7986fe95c.jpg?v=1569550040', 'is_main_image' => true],
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRSlr3L-rWbEZgW1WYZUbAa8oIDt2OIaOnGag&s', 'is_main_image' => false],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Carrots (1kg)',
            'slug' => Str::slug('Organic Carrots 1kg'),
            'short_description' => 'Sweet and crunchy organic carrots.',
            'description' => 'A kilogram of organic carrots, perfect for juicing, cooking, or snacking.',
            'price_per_unit' => 2.50,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 200.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'OVCARR002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.nutriharvest.com/cdn/shop/articles/carrots2_94041803-d158-41d7-8db2-57e6f2a1bb88.png?v=1712253710&width=1100', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Broccoli (500g)',
            'slug' => Str::slug('Organic Broccoli 500g'),
            'short_description' => 'Nutrient-rich organic broccoli florets.',
            'description' => 'Fresh 500g pack of organic broccoli, excellent for steaming, roasting, or stir-frying.',
            'price_per_unit' => 4.20,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 100.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'OVBROC003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTBCVOhmHEuhtT5Fs0ENgp2WR71Loh3q7e9qA&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Organic Kale (250g)',
            'slug' => Str::slug('Organic Kale 250g'),
            'short_description' => 'Superfood organic kale leaves.',
            'description' => 'Tender organic kale, perfect for salads, chips, or adding to soups.',
            'price_per_unit' => 3.50,
            'unit_of_measure' => 'bunch',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'OVKALE004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT6ar0eOWSBCE-OvR7afw4Fn8MZMt6sQLYmsw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id, // Still makes sense here as they are fresh vegetables/roots
            'name' => 'Fresh Yam (1 large tuber)',
            'slug' => Str::slug('Fresh Yam 1 large tuber'),
            'short_description' => 'Locally sourced large yam tuber.',
            'description' => 'A staple in Ghanaian cuisine, versatile for boiling, frying, or pounding for Fufu. Approx. 2-3kg.',
            'price_per_unit' => 15.00,
            'unit_of_measure' => 'tuber',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 50.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSYAM001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.freshplaza.com/remote/https/agfstorage.blob.core.windows.net/misc/FP_com/2024/09/06/maphlix1.jpg?preset=ContentFullSmall', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Cassava (1kg)',
            'slug' => Str::slug('Cassava 1kg'),
            'short_description' => 'Fresh cassava roots.',
            'description' => 'A kilogram of fresh cassava, essential for making Banku, Gari, or Fufu.',
            'price_per_unit' => 7.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 100.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSCAS002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://i0.wp.com/al-maghtasinvestment.com/wp-content/uploads/2021/08/20220402134321.jpg?resize=518%2C450&ssl=1', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Plantain (Ripe, 1 bunch)',
            'slug' => Str::slug('Plantain Ripe 1 bunch'),
            'short_description' => 'Sweet, ripe plantain bunch.',
            'description' => 'A bunch of ripe plantains, perfect for frying (Kelewele), mashing, or stewing.',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'bunch',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSPLN003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://nutritionstudies.org/wp-content/uploads/2019/04/5-ways-to-prepare-ripe-plantains-1.jpg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Cocoyam (1kg)',
            'slug' => Str::slug('Cocoyam 1kg'),
            'short_description' => 'Fresh cocoyam tubers.',
            'description' => 'A kilogram of cocoyam, a versatile tuber used in soups, stews, and as a fufu alternative.',
            'price_per_unit' => 8.50,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 70.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSCOCOY004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS_MHAGkdNwadmUW4FyDQX0YSTcjpaqW40qaw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Garden Eggs (500g)',
            'slug' => Str::slug('Garden Eggs 500g'),
            'short_description' => 'Fresh Ghanaian garden eggs.',
            'description' => '500g of fresh garden eggs, a key ingredient in many Ghanaian stews and sauces like Garden Egg Stew.',
            'price_per_unit' => 6.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSGGEGGS005',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRSsVRQpZQPqHU6JqWnhgrJOSwuvrqpDvBDaw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $organicVegetables->id,
            'name' => 'Kontomire (Taro Leaves, 1 bunch)',
            'slug' => Str::slug('Kontomire Taro Leaves 1 bunch'),
            'short_description' => 'Fresh taro leaves for kontomire stew.',
            'description' => 'A fresh bunch of kontomire (cocoyam leaves), essential for the popular Ghanaian Kontomire stew (Palava Sauce).',
            'price_per_unit' => 7.50,
            'unit_of_measure' => 'bunch',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 60.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSKONTO006',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSo7LqZ6C_BKR_8PgNixkC1v-BRxB34n9U2sA&s', 'is_main_image' => true],
        ]);


        // Milk & Cheese
        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Fresh Whole Milk (1L)',
            'slug' => Str::slug('Fresh Whole Milk 1L'),
            'short_description' => 'Farm-fresh whole milk.',
            'description' => 'Pasteurized and homogenized whole milk, delivered fresh daily. Rich in calcium.',
            'price_per_unit' => 1.80,
            'unit_of_measure' => 'liter',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 300.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'DWMILK001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQnZ4XYIQflCUkYYrYTrGzyE4vIun1u9nH1pg&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Cheddar Cheese Block (250g)',
            'slug' => Str::slug('Cheddar Cheese Block 250g'),
            'short_description' => 'Sharp and creamy cheddar cheese.',
            'description' => 'A versatile block of aged cheddar cheese, perfect for sandwiches, grating, or snacking.',
            'price_per_unit' => 5.99,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'DWCCHD002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.shoplongino.hk/media/catalog/product/1/8/18078_1.png?optimize=high&bg-color=255,255,255&fit=bounds&height=580&width=580&canvas=580:580&format=jpeg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Organic Greek Yogurt (500g)',
            'slug' => Str::slug('Organic Greek Yogurt 500g'),
            'short_description' => 'Thick and creamy organic Greek yogurt.',
            'description' => 'High-protein organic Greek yogurt, ideal for breakfast or healthy snacks.',
            'price_per_unit' => 4.50,
            'unit_of_measure' => 'tub',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 120.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'DWMILK003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTuVoTzQ5VwUkV81f83hJmiiQA0IwIbKV0bHw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $milkAndCheese->id,
            'name' => 'Mozzarella Ball (125g)',
            'slug' => Str::slug('Mozzarella Ball 125g'),
            'short_description' => 'Fresh, soft mozzarella cheese.',
            'description' => 'Delicate mozzarella ball, perfect for Caprese salads, pizzas, or snacks.',
            'price_per_unit' => 3.25,
            'unit_of_measure' => 'ball',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 60.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'DWCCHD004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://shop.wattsfarms.co.uk/cdn/shop/products/MozzarellaBall_1400x.jpg?v=1613552398', 'is_main_image' => true],
        ]);


        // Red Meat
        $product = Product::create([
            'category_id' => $redMeat->id,
            'name' => 'Grass-Fed Beef Mince (500g)',
            'slug' => Str::slug('Grass-Fed Beef Mince 500g'),
            'short_description' => 'Lean grass-fed beef mince.',
            'description' => 'Premium quality grass-fed beef mince, ideal for burgers, bolognese, or meatballs.',
            'price_per_unit' => 8.75,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 70.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'APRFM001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.meatmerchantcasuarina.com.au/cdn/shop/files/mince.jpg?v=1715483671', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $redMeat->id,
            'name' => 'Lamb Chops (300g)',
            'slug' => Str::slug('Lamb Chops 300g'),
            'short_description' => 'Tender lamb chops, perfect for grilling.',
            'description' => 'Delicious and succulent lamb chops, great for a quick and flavorful meal.',
            'price_per_unit' => 12.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 40.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'APRLAMB002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQhRydPUsKqfv9o1QpiXU-0Nx9vjqcBfcqGSg&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $redMeat->id,
            'name' => 'Pork Sausages (500g)',
            'slug' => Str::slug('Pork Sausages 500g'),
            'short_description' => 'Flavorful pork sausages.',
            'description' => 'Traditional pork sausages, excellent for breakfast or a hearty dinner.',
            'price_per_unit' => 6.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'APRPORK003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://primecuts.co.ke/cdn/shop/products/PORKSAUSAGES_500g_2048x.jpg?v=1596724666', 'is_main_image' => true],
        ]);

        // Fresh Fruits
        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Organic Apples (1kg)',
            'slug' => Str::slug('Organic Apples 1kg'),
            'short_description' => 'Crisp and sweet organic apples.',
            'description' => 'A kilogram of delicious, organic apples, perfect for snacking or baking.',
            'price_per_unit' => 3.20,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 180.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'FFAPP001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.thefruitcompany.com/cdn/shop/files/OrganicAppleMedley-SKUPF1006-01-NoBadge.png?v=1744244450', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Organic Bananas (1kg)',
            'slug' => Str::slug('Organic Bananas 1kg'),
            'short_description' => 'Naturally sweet organic bananas.',
            'description' => 'A kilogram of ripe organic bananas, great for snacks, smoothies, or baking.',
            'price_per_unit' => 2.80,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'FFBAN002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRdiGbsfyIpkm02A4dPZYT0liEYClDRzQVXVA&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Organic Oranges (1kg)',
            'slug' => Str::slug('Organic Oranges 1kg'),
            'short_description' => 'Juicy organic oranges.',
            'description' => 'A kilogram of sweet and tangy organic oranges, perfect for juicing or snacking.',
            'price_per_unit' => 3.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 130.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'FFORA003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://images-cdn.ubuy.com.sa/634d1a2842acdc52c6098563-organic-navel-oranges-15-pounds.jpg', 'is_main_image' => true],
        ]);

        // NEW: Ghanaian Fresh Fruits
        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Local Mangoes (1kg)',
            'slug' => Str::slug('Local Mangoes 1kg'),
            'short_description' => 'Sweet and juicy seasonal mangoes.',
            'description' => 'A kilogram of fresh, ripe Ghanaian mangoes, perfect for snacking or desserts.',
            'price_per_unit' => 8.00,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 70.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSMNG004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSPd2apozQx6mtIFt-VZpTSb2OF9iuZmE_BCw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $freshFruits->id,
            'name' => 'Papaya (1 large fruit)',
            'slug' => Str::slug('Papaya 1 large fruit'),
            'short_description' => 'Sweet, tropical papaya.',
            'description' => 'A large, ripe papaya, rich in vitamins and great for breakfast or a refreshing snack. Approx. 1.5-2kg.',
            'price_per_unit' => 12.00,
            'unit_of_measure' => 'fruit',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 40.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSPAP005',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.gepaghana.org/import/wp-content/uploads/sites/2/2018/07/Papaya-1.jpg', 'is_main_image' => true],
        ]);


        // Poultry
        $product = Product::create([
            'category_id' => $poultry->id,
            'name' => 'Chicken Breast Fillets (500g)',
            'slug' => Str::slug('Chicken Breast Fillets 500g'),
            'short_description' => 'Lean and tender chicken breast.',
            'description' => '500g pack of boneless, skinless chicken breast fillets, ideal for grilling or stir-fries.',
            'price_per_unit' => 6.50,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 120.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'APCHICK001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://freerangers.co.za/wp-content/uploads/2019/07/breastfillets-1.jpg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $poultry->id,
            'name' => 'Whole Chicken (1.5kg)',
            'slug' => Str::slug('Whole Chicken 1.5kg'),
            'short_description' => 'Farm-fresh whole chicken.',
            'description' => 'A 1.5kg whole chicken, perfect for roasting or grilling for the family.',
            'price_per_unit' => 9.50,
            'unit_of_measure' => 'piece',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 50.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'APCHICK002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://order.syscoireland.com/media/catalog/product/cache/62d2258b37c8e3278a1b37c898b8c1e5/c/1/c170_1.jpg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $poultry->id,
            'name' => 'Chicken Thighs (500g)',
            'slug' => Str::slug('Chicken Thighs 500g'),
            'short_description' => 'Juicy chicken thighs, bone-in, skin-on.',
            'description' => '500g pack of flavorful chicken thighs, excellent for baking or braising.',
            'price_per_unit' => 5.80,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 100.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'APCHICK003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.freshwaysmu.com/wp-content/uploads/2020/04/Chicken-Thighs.png', 'is_main_image' => true],
        ]);

        // Seafood (now a proper category)
        $product = Product::create([
            'category_id' => $seafood->id,
            'name' => 'Smoked Tilapia (1 fish)',
            'slug' => Str::slug('Smoked Tilapia 1 fish'),
            'short_description' => 'Locally smoked whole tilapia.',
            'description' => 'A traditional smoked tilapia fish, adding rich flavor to Ghanaian soups and stews.',
            'price_per_unit' => 20.00,
            'unit_of_measure' => 'fish',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 30.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSSMOKETF001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://ghbasket.com/wp-content/uploads/2022/12/smoked-tilapia.jpg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $seafood->id,
            'name' => 'Fresh Salmon Fillet (200g)',
            'slug' => Str::slug('Fresh Salmon Fillet 200g'),
            'short_description' => 'Premium fresh salmon fillet.',
            'description' => '200g of high-quality fresh salmon fillet, great for grilling, baking, or pan-frying.',
            'price_per_unit' => 35.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 25.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSFISH002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTQJmHlLTS9f5-D8ewJhW1LB3KpIE3Ob_RSXg&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $seafood->id,
            'name' => 'Dried Herring (Amane, 250g)',
            'slug' => Str::slug('Dried Herring Momone 250g'),
            'short_description' => 'Authentic Ghanaian dried fermented herring.',
            'description' => '250g pack of "Momone", a traditional dried and fermented herring, used to add a unique, pungent flavor to Ghanaian stews and soups.',
            'price_per_unit' => 15.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 40.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSDRIEDHER003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcToN19I7gadb5EIQdAER-FK7JxdKRAFVPXoiA&s', 'is_main_image' => true], // Placeholder
        ]);

        // Cereal & Grains (General)
        $product = Product::create([
            'category_id' => $cerealAndGrains->id,
            'name' => 'Rolled Oats (1kg)',
            'slug' => Str::slug('Rolled Oats 1kg'),
            'short_description' => 'Hearty rolled oats for breakfast.',
            'description' => 'A kilogram of whole grain rolled oats, perfect for oatmeal, granola, or baking.',
            'price_per_unit' => 2.99,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'CGROATS001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT8MTE5joiSWyTsTiYq1p9-K_rHaFbAtNVsYw&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $cerealAndGrains->id,
            'name' => 'Brown Rice (1kg)',
            'slug' => Str::slug('Brown Rice 1kg'),
            'short_description' => 'Nutritious whole grain brown rice.',
            'description' => 'A kilogram of unpolished brown rice, rich in fiber and ideal for healthy meals.',
            'price_per_unit' => 3.50,
            'unit_of_measure' => 'kg',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 110.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'CGRICE002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://www.ghanabusinessnews.com/wp-content/uploads/2015/08/Screen-Shot-2015-08-18-at-10.44.25-AM.png', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $cerealAndGrains->id,
            'name' => 'Quinoa (500g)',
            'slug' => Str::slug('Quinoa 500g'),
            'short_description' => 'High-protein ancient grain.',
            'description' => '500g pack of versatile quinoa, perfect as a side dish, in salads, or for meal prep.',
            'price_per_unit' => 5.75,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 75.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'CGQUIN003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://nuttyyogi.com/cdn/shop/products/Quinoa_White.jpg?v=1677652891', 'is_main_image' => true],
        ]);


        // NEW: Local Grains & Flours (Under Pantry Staples)
        $product = Product::create([
            'category_id' => $localGrainsAndFlours->id,
            'name' => 'Gari (1kg bag)',
            'slug' => Str::slug('Gari 1kg bag'),
            'short_description' => 'Toasted granulated cassava.',
            'description' => 'A 1kg bag of gari, a popular fermented and toasted cassava granule, essential for Eba, Gari Soakings, and Kokonte.',
            'price_per_unit' => 8.00,
            'unit_of_measure' => 'bag',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 200.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSGARI001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://gw.alicdn.com/imgextra/i4/749215931/O1CN01IQFtv11tgRoHw30M9_!!749215931.jpg_Q75.jpg_.webp', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $localGrainsAndFlours->id,
            'name' => 'Dried Maize (1kg bag)',
            'slug' => Str::slug('Dried Maize 1kg bag'),
            'short_description' => 'Dried corn kernels.',
            'description' => 'A kilogram of dried maize, fundamental for preparing Banku, Kenkey, and local porridges.',
            'price_per_unit' => 6.50,
            'unit_of_measure' => 'bag',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSMAIZE002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://sokokuu.africa/images/detailed/9/WhatsApp_Image_2021-01-04_at_9.26.36_AM.jpeg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $localGrainsAndFlours->id,
            'name' => 'Millet (500g bag)',
            'slug' => Str::slug('Millet 500g bag'),
            'short_description' => 'Nutritious millet grains.',
            'description' => 'A 500g bag of millet, a healthy grain used for porridges (Millet Porridge/Koko) and other traditional dishes.',
            'price_per_unit' => 4.00,
            'unit_of_measure' => 'bag',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSMILLET003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSIG1cM3HnAcEmAeVMj1840j42hUBc4v8dq0A&s', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $localGrainsAndFlours->id,
            'name' => 'Cassava Flour (Kokonte Powder, 1kg)',
            'slug' => Str::slug('Cassava Flour Kokonte Powder 1kg'),
            'short_description' => 'Fine cassava flour for Kokonte.',
            'description' => 'A 1kg bag of dried and milled cassava flour, primarily used for preparing Kokonte, a popular Ghanaian staple.',
            'price_per_unit' => 9.00,
            'unit_of_measure' => 'bag',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSKOPOW004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTcpR_wZ8knR6ICkXBn79Bfbf8efJdVqKactA&s', 'is_main_image' => true], // Placeholder
        ]);

        // NEW: Oils & Condiments (Under Pantry Staples)
        $product = Product::create([
            'category_id' => $oilsAndCondiments->id,
            'name' => 'Red Palm Oil (1L bottle)',
            'slug' => Str::slug('Red Palm Oil 1L bottle'),
            'short_description' => 'Pure, unrefined red palm oil.',
            'description' => 'A 1-liter bottle of authentic red palm oil, crucial for preparing Ghanaian stews and soups like Kontomire stew and Palm Nut Soup.',
            'price_per_unit' => 25.00,
            'unit_of_measure' => 'bottle',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 60.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSOIL001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://img.favpng.com/9/21/10/palm-oil-palm-kernel-oil-cooking-oils-african-oil-palm-png-favpng-1u2M3CcsGcV6zdUtK41uW9kPq.jpg', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $oilsAndCondiments->id,
            'name' => 'Shito (Black Pepper Sauce, 250g jar)',
            'slug' => Str::slug('Shito Black Pepper Sauce 250g jar'),
            'short_description' => 'Spicy Ghanaian black pepper sauce.',
            'description' => 'A 250g jar of homemade-style Shito, a popular spicy black pepper sauce, perfect with Kenkey, rice, or fried plantain.',
            'price_per_unit' => 18.00,
            'unit_of_measure' => 'jar',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 50.00,
            'is_featured' => true,
            'is_active' => true,
            'sku' => 'GHSSHITO001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSjizJlO-WRG6ZbgZ1TmAZHE6U5zB9__XFG9g&s', 'is_main_image' => true], // Placeholder
        ]);

        $product = Product::create([
            'category_id' => $oilsAndCondiments->id,
            'name' => 'Palm Nut Concentrate (400g can)',
            'slug' => Str::slug('Palm Nut Concentrate 400g can'),
            'short_description' => 'Concentrated palm nut extract.',
            'description' => 'A 400g can of ready-to-use palm nut concentrate, making preparation of traditional Palm Nut Soup (Abenkwan) easier and quicker.',
            'price_per_unit' => 10.00,
            'unit_of_measure' => 'can',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 70.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSPNC002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://ghbasket.com/wp-content/uploads/2021/06/prekese-405x330.jpg', 'is_main_image' => true], // Placeholder
        ]);

        // NEW: Dried & Processed Foods (Under Pantry Staples)
        $product = Product::create([
            'category_id' => $driedAndProcessedFoods->id,
            'name' => 'Dried Herrings Powder (100g pack)',
            'slug' => Str::slug('Dried Herrings Powder Small 100g pack'),
            'short_description' => 'Small dried fish, adds umami.',
            'description' => '100g pack of small dried herrings, commonly used in Ghanaian stews and sauces for a savory depth of flavor.',
            'price_per_unit' => 8.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 90.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSDHER001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://mrfishprocessinghub.com/wp-content/uploads/2024/04/MRFISH0407-300x300.jpg', 'is_main_image' => true], // Placeholder
        ]);

        $product = Product::create([
            'category_id' => $driedAndProcessedFoods->id,
            'name' => 'Tomato Paste (Double Concentrate, 400g can)',
            'slug' => Str::slug('Tomato Paste Double Concentrate 400g can'),
            'short_description' => 'Rich, concentrated tomato paste.',
            'description' => 'A 400g can of double concentrated tomato paste, a fundamental ingredient for Ghanaian Jollof, stews, and soups.',
            'price_per_unit' => 7.00,
            'unit_of_measure' => 'can',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSTOMPASTE002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSrq65tuXa7GywPWYfkMUhkzH-iDTPr9h6kuQ&s', 'is_main_image' => true], // Placeholder
        ]);

        // NEW: Spices & Seasonings (Under Pantry Staples)
        $product = Product::create([
            'category_id' => $spicesAndSeasonings->id,
            'name' => 'Fresh Ginger (250g)',
            'slug' => Str::slug('Fresh Ginger 250g'),
            'short_description' => 'A quarter kilogram of fresh ginger.',
            'description' => 'Pungent and aromatic ginger, essential for Ghanaian stews, soups, and drinks like Sobolo.',
            'price_per_unit' => 5.50,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 150.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSSPGING001',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://images.pexels.com/photos/10112135/pexels-photo-10112135.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1', 'is_main_image' => true],
        ]);

        $product = Product::create([
            'category_id' => $spicesAndSeasonings->id,
            'name' => 'Fresh Garlic (250g)',
            'slug' => Str::slug('Fresh Garlic 250g'),
            'short_description' => 'A quarter kilogram of fresh garlic.',
            'description' => 'Versatile fresh garlic, a fundamental aromatic in Ghanaian cooking, used in most stews and sauces.',
            'price_per_unit' => 4.50,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 130.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSSPURLIC002',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://hips.hearstapps.com/hmg-prod/images/garlic-braid-1532701804.jpg', 'is_main_image' => true], // Placeholder
        ]);

        $product = Product::create([
            'category_id' => $spicesAndSeasonings->id,
            'name' => 'Hot Pepper (Fresh, 200g)',
            'slug' => Str::slug('Hot Pepper Fresh 200g'),
            'short_description' => 'Fresh, fiery hot peppers.',
            'description' => '200g of fresh hot peppers (Kpakpo Shito or similar), crucial for adding heat to your Ghanaian dishes like Shito, stews, and soups.',
            'price_per_unit' => 9.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 80.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSSPPEP003',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://uprisingorganics.com/cdn/shop/products/hotportugal.jpg?v=1606175526', 'is_main_image' => true], // Placeholder
        ]);

        $product = Product::create([
            'category_id' => $spicesAndSeasonings->id,
            'name' => 'Dawadawa (Fermented Dawadawa Beans, 100g pack)',
            'slug' => Str::slug('Dawadawa Fermented Dawadawa Beans 100g pack'),
            'short_description' => 'Traditional West African seasoning.',
            'description' => '100g pack of Dawadawa, fermented African locust beans, offering a unique umami and savory depth to Ghanaian soups and stews.',
            'price_per_unit' => 7.00,
            'unit_of_measure' => 'pack',
            'min_order_quantity' => 1.00,
            'stock_quantity' => 50.00,
            'is_featured' => false,
            'is_active' => true,
            'sku' => 'GHSSDADA004',
        ]);
        $product->images()->createMany([
            ['image_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ5kvhAfPMMq5mF47py8VbdpSGYZ8LztLUNcA&s', 'is_main_image' => true], // Placeholder
        ]);

    }
}