<?php

namespace Database\Seeders;

use App\Models\AgentLog;
use App\Models\Category;
use App\Models\DonationPartner;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Categories
        $categoriesData = [
            ['name' => 'Buah dan Sayur', 'slug' => 'fresh-produce'],
            ['name' => 'Roti dan Kue', 'slug' => 'bakery'],
            ['name' => 'Susu dan Telur', 'slug' => 'dairy-eggs'],
            ['name' => 'Bahan Pangan Kemasan', 'slug' => 'packaged-goods'],
            ['name' => 'Daging dan Hasil Laut', 'slug' => 'meat-seafood'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['slug']] = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        // 2. Define Products across expiration tiers
        $productsData = [
            // Tier 1: Expiring in 1-2 days
            [
                'category_slug' => 'dairy-eggs',
                'sku' => 'ECL-DRY-1001',
                'name' => 'Susu UHT 1 L',
                'description' => 'Susu UHT untuk konsumsi harian.',
                'quantity' => 18,
                'unit' => 'liter',
                'original_price' => 20000,
                'discount_price' => 10000,
                'expiration_date' => now()->addDays(1)->toDateString(),
                'status' => 'discounted',
                'agent_action' => [
                    'action_type' => 'discount_applied',
                    'reasoning' => 'Gemini AI Agent detected shelf-life < 36h. Applied 50% dynamic markdown to accelerate consumer velocity before waste threshold.',
                    'payload' => ['original_price' => 20000, 'discount_price' => 10000, 'markdown_pct' => 50, 'rule' => 'expiry_lt_36h'],
                    'status' => 'success',
                ],
            ],
            [
                'category_slug' => 'bakery',
                'sku' => 'ECL-BAK-2001',
                'name' => 'Roti Sourdough 500 g',
                'description' => 'Roti sourdough yang dipanggang segar.',
                'quantity' => 12,
                'unit' => 'buah',
                'original_price' => 28000,
                'discount_price' => 14000,
                'expiration_date' => now()->addDays(1)->toDateString(),
                'status' => 'discounted',
                'agent_action' => [
                    'action_type' => 'discount_applied',
                    'reasoning' => 'Automated clearance rule: Artisan bakery item entering final 24 hours marked down by 50%.',
                    'payload' => ['original_price' => 28000, 'discount_price' => 14000, 'markdown_pct' => 50, 'rule' => 'bakery_evening_markdown'],
                    'status' => 'success',
                ],
            ],
            [
                'category_slug' => 'fresh-produce',
                'sku' => 'ECL-PRD-3001',
                'name' => 'Alpukat Matang 1 kg',
                'description' => 'Alpukat matang siap dikonsumsi.',
                'quantity' => 25,
                'unit' => 'kg',
                'original_price' => 35000,
                'discount_price' => 21000,
                'expiration_date' => now()->addDays(2)->toDateString(),
                'status' => 'discounted',
                'agent_action' => [
                    'action_type' => 'discount_applied',
                    'reasoning' => 'Ripeness index 4/5 reached. 40% promotional markdown recommended to prevent spoilage.',
                    'payload' => ['original_price' => 35000, 'discount_price' => 21000, 'markdown_pct' => 40, 'rule' => 'ripeness_spoilage_risk'],
                    'status' => 'success',
                ],
            ],
            [
                'category_slug' => 'meat-seafood',
                'sku' => 'ECL-MEA-4001',
                'name' => 'Fillet Ikan Salmon 500 g',
                'description' => 'Fillet ikan salmon segar, disimpan dalam rantai dingin.',
                'quantity' => 8,
                'unit' => 'bungkus',
                'original_price' => 85000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(1)->toDateString(),
                'status' => 'reserved_donation',
                'agent_action' => [
                    'action_type' => 'donation_requested',
                    'reasoning' => 'High-protein item expiring within 24 hours reserved for immediate cold-chain pickup by Community Kitchen.',
                    'payload' => ['partner' => 'Community Kitchen & Shelter', 'pickup_deadline' => now()->addHours(12)->toIso8601String(), 'quantity_kg' => 8],
                    'status' => 'success',
                ],
            ],
            [
                'category_slug' => 'bakery',
                'sku' => 'ECL-BAK-2002',
                'name' => 'Roti Baguette',
                'description' => 'Roti baguette dengan kulit renyah.',
                'quantity' => 15,
                'unit' => 'buah',
                'original_price' => 18000,
                'discount_price' => 9000,
                'expiration_date' => now()->addDays(1)->toDateString(),
                'status' => 'discounted',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'fresh-produce',
                'sku' => 'ECL-PRD-3002',
                'name' => 'Bayam Segar 200 g',
                'description' => 'Bayam segar siap dimasak.',
                'quantity' => 14,
                'unit' => 'bungkus',
                'original_price' => 12000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(2)->toDateString(),
                'status' => 'active',
                'agent_action' => [
                    'action_type' => 'alert_sent',
                    'reasoning' => 'Early warning: Baby spinach inventory volume is 14 packs with 48h shelf life remaining. Monitor for markdown at 18:00.',
                    'payload' => ['shelf_life_remaining_hours' => 48, 'projected_waste_risk' => 'medium'],
                    'status' => 'success',
                ],
            ],

            // Tier 2: Expiring in 3-5 days
            [
                'category_slug' => 'dairy-eggs',
                'sku' => 'ECL-DRY-1002',
                'name' => 'Yogurt Tawar 500 g',
                'description' => 'Yogurt tawar untuk konsumsi harian.',
                'quantity' => 22,
                'unit' => 'buah',
                'original_price' => 25000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(3)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'fresh-produce',
                'sku' => 'ECL-PRD-3003',
                'name' => 'Stroberi 250 g',
                'description' => 'Stroberi segar hasil panen lokal.',
                'quantity' => 30,
                'unit' => 'bungkus',
                'original_price' => 25000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(3)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'bakery',
                'sku' => 'ECL-BAK-2003',
                'name' => 'Roti Gandum 600 g',
                'description' => 'Roti gandum iris untuk sarapan dan bekal.',
                'quantity' => 20,
                'unit' => 'buah',
                'original_price' => 22000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(4)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'meat-seafood',
                'sku' => 'ECL-MEA-4002',
                'name' => 'Daging Sapi Giling 500 g',
                'description' => 'Daging sapi giling segar.',
                'quantity' => 15,
                'unit' => 'bungkus',
                'original_price' => 65000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(4)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'dairy-eggs',
                'sku' => 'ECL-DRY-1003',
                'name' => 'Telur Ayam 12 Butir',
                'description' => 'Telur ayam untuk kebutuhan dapur sehari-hari.',
                'quantity' => 40,
                'unit' => 'butir',
                'original_price' => 30000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(5)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'fresh-produce',
                'sku' => 'ECL-PRD-3004',
                'name' => 'Tomat Ceri 500 g',
                'description' => 'Tomat ceri segar untuk salad dan masakan.',
                'quantity' => 28,
                'unit' => 'bungkus',
                'original_price' => 18000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(4)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],

            // Tier 3: Expiring in > 7 days
            [
                'category_slug' => 'dairy-eggs',
                'sku' => 'ECL-DRY-1004',
                'name' => 'Keju Cheddar 250 g',
                'description' => 'Keju cheddar untuk masakan dan camilan.',
                'quantity' => 24,
                'unit' => 'buah',
                'original_price' => 45000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(14)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'fresh-produce',
                'sku' => 'ECL-PRD-3005',
                'name' => 'Apel 1 kg',
                'description' => 'Apel segar untuk camilan dan bekal.',
                'quantity' => 45,
                'unit' => 'kg',
                'original_price' => 35000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(10)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'packaged-goods',
                'sku' => 'ECL-PKG-5001',
                'name' => 'Minyak Zaitun 750 ml',
                'description' => 'Minyak zaitun untuk memasak dan campuran makanan.',
                'quantity' => 35,
                'unit' => 'buah',
                'original_price' => 125000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(60)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'packaged-goods',
                'sku' => 'ECL-PKG-5002',
                'name' => 'Sereal Oat 1 kg',
                'description' => 'Oat utuh untuk sarapan.',
                'quantity' => 50,
                'unit' => 'bungkus',
                'original_price' => 35000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(90)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'packaged-goods',
                'sku' => 'ECL-PKG-5003',
                'name' => 'Tomat Kalengan 400 g',
                'description' => 'Tomat kalengan untuk bahan masakan.',
                'quantity' => 120,
                'unit' => 'buah',
                'original_price' => 22000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(180)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            [
                'category_slug' => 'packaged-goods',
                'sku' => 'ECL-PKG-5004',
                'name' => 'Beras Merah 2 kg',
                'description' => 'Beras merah untuk konsumsi sehari-hari.',
                'quantity' => 40,
                'unit' => 'bungkus',
                'original_price' => 38000,
                'discount_price' => null,
                'expiration_date' => now()->addDays(120)->toDateString(),
                'status' => 'active',
                'agent_action' => null,
            ],
            // Historic / Completed donation
            [
                'category_slug' => 'bakery',
                'sku' => 'ECL-BAK-2004',
                'name' => 'Croissant 6 Buah',
                'description' => 'Croissant mentega yang dikemas dalam satu baki.',
                'quantity' => 10,
                'unit' => 'bungkus',
                'original_price' => 42000,
                'discount_price' => null,
                'expiration_date' => now()->subDay()->toDateString(),
                'status' => 'donated',
                'agent_action' => [
                    'action_type' => 'donation_requested',
                    'reasoning' => 'End of day surplus dispatched to Orphanage Care Haven. Confirmed delivery at 20:30.',
                    'payload' => ['recipient' => 'Orphanage Care Haven', 'delivered_at' => now()->subDay()->toIso8601String(), 'status' => 'completed'],
                    'status' => 'success',
                ],
            ],
        ];

        foreach ($productsData as $data) {
            $category = $categories[$data['category_slug']];
            $agentAction = $data['agent_action'];
            unset($data['category_slug'], $data['agent_action']);

            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                array_merge($data, ['category_id' => $category->id])
            );

            if ($agentAction) {
                $partnerId = null;
                if (! empty($agentAction['payload']['partner_id'])) {
                    $partnerId = $agentAction['payload']['partner_id'];
                } elseif (! empty($agentAction['payload']['recipient'])) {
                    $partnerId = DonationPartner::where('name', $agentAction['payload']['recipient'])->value('id');
                }

                AgentLog::create(array_merge($agentAction, [
                    'product_id' => $product->id,
                    'partner_id' => $partnerId,
                ]));
            }
        }
    }
}
