<?php

namespace App\Services;

use App\Models\DonationPartner;
use App\Models\Product;
use App\Models\ProductBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class AiAgentService
{
    /**
     * Identify a stocked product from an image and return a separate analysis and recommendation.
     *
     * @param  Collection<int, ProductBatch>  $batches
     * @return array{matched_product_id: int|null, donation_eligible: bool, confidence: float, match_confidence: float, condition_confidence: float, analysis_available: bool, analysis: string, recommendation: string, condition: string, reasoning: string, safety_note: string, provider: string}
     */
    public function identifyAndEvaluateDonationPhoto(Collection $batches, string $imageData, string $mimeType, bool $partnerMode = false): array
    {
        $manualReview = [
            'matched_product_id' => null,
            'donation_eligible' => false,
            'confidence' => 0.0,
            'match_confidence' => 0.0,
            'condition_confidence' => 0.0,
            'analysis_available' => false,
            'analysis' => 'AI belum berhasil menganalisis foto.',
            'recommendation' => 'Jangan mengambil atau mengubah stok berdasarkan hasil yang belum tersedia. Periksa produk secara manual atau coba lagi.',
            'condition' => 'unclear',
            'reasoning' => 'AI belum berhasil menganalisis foto.',
            'safety_note' => 'Foto tidak dapat memastikan keamanan mikrobiologis, bau, suhu penyimpanan, maupun kontaminasi makanan.',
            'provider' => 'manual_review',
        ];

        if (! $this->hasNineRouterKey()) {
            return [
                ...$manualReview,
                'reasoning' => 'Konfigurasi 9Router belum tersedia untuk analisis gambar.',
                'analysis' => 'Konfigurasi 9Router belum tersedia untuk analisis gambar.',
                'recommendation' => 'Periksa NINEROUTER_API_KEY dan NINEROUTER_VISION_MODEL sebelum mencoba kembali.',
            ];
        }

        if ($batches->isEmpty()) {
            return [
                ...$manualReview,
                'reasoning' => 'Tidak ada batch stok aktif yang dapat dicocokkan dengan foto.',
                'analysis' => 'Tidak ada batch stok aktif yang dapat dicocokkan dengan foto.',
                'recommendation' => 'Periksa daftar stok aktif atau tambahkan batch produk terlebih dahulu.',
            ];
        }

        $products = $batches
            ->unique('product_id')
            ->map(fn (ProductBatch $batch): Product => $batch->product)
            ->filter()
            ->values();
        $catalog = $products->map(fn (Product $product): array => [
            'candidate_id' => (string) $product->id,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'category' => (string) ($product->category?->name ?? 'Makanan'),
        ])->all();
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $partnerRule = $partnerMode
            ? 'Untuk mitra donasi, good maupun acceptable dapat ditawarkan untuk diambil jika tampak layak konsumsi; mitra tetap memeriksa langsung saat serah terima.'
            : 'Untuk staf supermarket, hanya acceptable dapat direkomendasikan untuk donasi; good masih layak jual dan poor atau unclear tidak boleh didonasikan.';

        $prompt = <<<PROMPT
Analisis foto dan pilih produk yang paling cocok dari katalog stok. Hanya gunakan candidate_id, SKU, dan nama yang benar-benar ada di katalog. Jangan mengarang produk atau ID.
[KATALOG STOK]
{$catalogJson}

Tentukan dua skor secara terpisah:
- match_confidence: kemiripan objek pada foto dengan satu produk katalog (0 sampai 1).
- condition_confidence: keyakinan atas kondisi visual produk (0 sampai 1).
Jika match_confidence lebih dari 0.50, pilih candidate_id produk tersebut. Jika tidak ada kandidat yang cocok di atas 0.50, candidate_id=null.
Kondisi:
- good: tampak layak dikonsumsi dan masih layak dijual.
- acceptable: tampak tidak layak dijual karena kematangan atau cacat tampilan ringan, tetapi masih berpotensi layak dikonsumsi.
- poor: tampak busuk, berjamur, rusak berat, atau ada tanda tidak layak dikonsumsi.
- unclear: foto, produk, atau kondisi tidak cukup jelas.
{$partnerRule}
Jangan memilih acceptable hanya karena tanggal kedaluwarsa dekat. Foto tidak membuktikan keamanan makanan. Jika ragu, minta pemeriksaan manual.
Berikan analisis singkat berdasarkan hal yang benar-benar terlihat dan rekomendasi tindakan yang jelas dalam Bahasa Indonesia. Jangan menyatakan makanan pasti aman.
Kembalikan hanya JSON valid dengan key candidate_id (string atau null), candidate_name, match_confidence, condition_confidence, condition, donation_eligible, analysis, recommendation, safety_note.
PROMPT;

        try {
            $model = (string) config('services.ninerouter.vision_model');
            $baseUrl = rtrim((string) config('services.ninerouter.base_url', 'http://127.0.0.1:20128/v1'), '/');
            $response = Http::timeout(60)
                ->withToken((string) config('services.ninerouter.api_key'))
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a careful visual product-matching and food-condition assistant for SirkulaFood. Select only an exact product from the catalog, return Indonesian analysis and recommendation, and never claim that a photo proves food safety. Return strict JSON only.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mimeType.';base64,'.$imageData, 'detail' => 'high']],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                $upstreamMessage = mb_strtolower((string) $response->json('error.message', ''));
                $quotaLimited = $response->status() === 429
                    || str_contains($upstreamMessage, 'free-models-per-day')
                    || str_contains($upstreamMessage, 'rate limit');
                $reasoning = $quotaLimited
                    ? 'Analisis tidak dijalankan karena kuota model visi 9Router sedang habis.'
                    : ($response->status() === 503
                        ? 'Layanan model visi 9Router sedang tidak tersedia.'
                        : 'Model visi 9Router menolak permintaan analisis foto (HTTP '.$response->status().').');
                $recommendation = $quotaLimited
                    ? 'Coba lagi setelah kuota model pulih, atau pilih model vision lain yang masih tersedia di 9Router.'
                    : 'Periksa ketersediaan model vision dan dukungan input gambar di 9Router, lalu coba foto ulang.';

                Log::warning('9Router inventory photo identification returned an error.', [
                    'status' => $response->status(),
                    'quota_limited' => $quotaLimited,
                    'model' => $model,
                ]);

                return [
                    ...$manualReview,
                    'analysis' => $reasoning,
                    'reasoning' => $reasoning,
                    'recommendation' => $recommendation,
                    'provider' => '9router_unavailable',
                ];
            }

            $rawText = $response->json('choices.0.message.content');
            if (is_array($rawText)) {
                $rawText = collect($rawText)
                    ->pluck('text')
                    ->filter(fn ($text): bool => is_string($text))
                    ->implode("\n");
            }
            if (! is_string($rawText) || trim($rawText) === '') {
                return [
                    ...$manualReview,
                    'provider' => '9router:'.$model,
                    'reasoning' => 'Model merespons tetapi tidak mengirimkan hasil analisis yang dapat dibaca.',
                    'analysis' => 'Model merespons tetapi tidak mengirimkan hasil analisis yang dapat dibaca.',
                    'recommendation' => 'Coba model vision lain di 9Router atau ambil foto ulang dengan produk terlihat jelas.',
                ];
            }

            $decision = $this->decodeVisionDecision($rawText);
            if ($decision === null) {
                return [
                    ...$manualReview,
                    'provider' => '9router:'.$model,
                    'reasoning' => 'Respons model tidak terbaca sebagai JSON analisis yang valid.',
                    'analysis' => 'Respons model tidak terbaca sebagai JSON analisis yang valid.',
                    'recommendation' => 'Coba model vision lain di 9Router atau ambil foto ulang dengan produk terlihat jelas.',
                ];
            }

            $matchConfidence = $this->normalizeVisionScore(
                $decision['match_confidence']
                    ?? $decision['similarity_score']
                    ?? $decision['similarity']
                    ?? $decision['confidence']
                    ?? 0
            );
            $conditionConfidence = $this->normalizeVisionScore(
                $decision['condition_confidence']
                    ?? $decision['food_condition_confidence']
                    ?? $decision['confidence']
                    ?? $matchConfidence
            );
            $matchedProduct = $this->matchVisionCandidate($decision, $products);
            $isMatched = $matchedProduct !== null && $matchConfidence > 0.50;
            $condition = $this->normalizeVisionCondition($decision['condition'] ?? null);
            $allowedConditions = $partnerMode ? ['good', 'acceptable'] : ['acceptable'];
            $modelAllowsDonation = filter_var($decision['donation_eligible'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $eligible = $isMatched
                && $modelAllowsDonation
                && $conditionConfidence >= 0.75
                && in_array($condition, $allowedConditions, true);
            $analysis = $this->visionText($decision['analysis'] ?? $decision['reasoning'] ?? null)
                ?? ($isMatched
                    ? 'Foto paling mirip dengan '.$matchedProduct->name.'. Kondisi visual dinilai '.$condition.'.'
                    : 'Foto belum cocok dengan produk stok di atas ambang kemiripan 50%.');
            $recommendation = $this->visionText($decision['recommendation'] ?? null)
                ?? $this->fallbackVisionRecommendation($condition, $partnerMode, $isMatched);
            $safetyNote = $this->visionText($decision['safety_note'] ?? null)
                ?? $manualReview['safety_note'];

            return [
                'matched_product_id' => $isMatched ? (int) $matchedProduct->id : null,
                'donation_eligible' => $eligible,
                'confidence' => $matchConfidence,
                'match_confidence' => $matchConfidence,
                'condition_confidence' => $conditionConfidence,
                'analysis_available' => true,
                'analysis' => $analysis,
                'recommendation' => $recommendation,
                'condition' => $condition,
                'reasoning' => $analysis,
                'safety_note' => $safetyNote,
                'provider' => '9router:'.$model,
            ];
        } catch (Throwable $exception) {
            Log::warning('9Router inventory photo identification failed.', [
                'error' => $exception->getMessage(),
                'model' => config('services.ninerouter.vision_model'),
            ]);

            $reasoning = 'Layanan analisis gambar 9Router tidak merespons. Coba lagi atau gunakan model vision lain.';

            return [
                ...$manualReview,
                'analysis' => $reasoning,
                'reasoning' => $reasoning,
                'recommendation' => 'Tidak ada produk yang dianggap cocok dan stok tidak berubah. Coba foto ulang setelah layanan pulih.',
                'provider' => '9router_unavailable',
            ];
        }
    }

    private function decodeVisionDecision(string $rawText): ?array
    {
        $cleaned = trim(preg_replace('/^\xEF\xBB\xBF/u', '', trim($rawText)) ?? trim($rawText));
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
        $decision = json_decode($cleaned, true);
        if (is_array($decision)) {
            return $decision;
        }

        if (is_string($decision) && $decision !== $cleaned) {
            $nestedDecision = $this->decodeVisionDecision($decision);
            if ($nestedDecision !== null) {
                return $nestedDecision;
            }
        }

        $length = strlen($cleaned);
        for ($start = 0; $start < $length; $start++) {
            if ($cleaned[$start] !== '{') {
                continue;
            }

            $depth = 0;
            $insideString = false;
            $escaped = false;
            for ($end = $start; $end < $length; $end++) {
                $character = $cleaned[$end];
                if ($insideString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($character === '\\') {
                        $escaped = true;
                    } elseif ($character === '"') {
                        $insideString = false;
                    }

                    continue;
                }

                if ($character === '"') {
                    $insideString = true;
                } elseif ($character === '{') {
                    $depth++;
                } elseif ($character === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $decision = json_decode(substr($cleaned, $start, $end - $start + 1), true);
                        if (is_array($decision)) {
                            return $decision;
                        }

                        break;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeVisionScore(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $score = (float) $value;
        if ($score > 1 && $score <= 100) {
            $score /= 100;
        }

        return max(0.0, min(1.0, $score));
    }

    private function normalizeVisionCondition(mixed $value): string
    {
        $condition = mb_strtolower(trim((string) $value));

        return match ($condition) {
            'good', 'fresh', 'saleable', 'sellable', 'layak jual' => 'good',
            'acceptable', 'fair', 'not_salable', 'not for sale', 'layak donasi' => 'acceptable',
            'poor', 'spoiled', 'rotten', 'unsafe', 'rusak' => 'poor',
            default => 'unclear',
        };
    }

    private function matchVisionCandidate(array $decision, Collection $products): ?Product
    {
        foreach (['candidate_id', 'matched_product_id', 'product_id', 'sku', 'candidate_sku'] as $field) {
            $reference = $decision[$field] ?? null;
            if (! is_scalar($reference) || trim((string) $reference) === '') {
                continue;
            }

            $reference = trim((string) $reference);
            $product = $products->first(fn (Product $candidate): bool => (string) $candidate->id === $reference || strcasecmp((string) $candidate->sku, $reference) === 0
            );
            if ($product instanceof Product) {
                return $product;
            }
        }

        foreach (['candidate_name', 'matched_product_name', 'product_name', 'name'] as $field) {
            $name = $this->visionText($decision[$field] ?? null);
            if ($name === null) {
                continue;
            }

            $normalizedName = $this->normalizeVisionName($name);
            $product = $products->first(fn (Product $candidate): bool => $this->normalizeVisionName((string) $candidate->name) === $normalizedName
            );
            if ($product instanceof Product) {
                return $product;
            }
        }

        return null;
    }

    private function normalizeVisionName(string $name): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($name))) ?? '';

        return trim($normalized);
    }

    private function visionText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function fallbackVisionRecommendation(string $condition, bool $partnerMode, bool $matched): string
    {
        if (! $matched) {
            return 'Ambil foto ulang dengan satu produk terlihat jelas. Stok tidak berubah selama produk belum cocok dengan kemiripan di atas 50%.';
        }

        return match ($condition) {
            'good' => $partnerMode
                ? 'Produk cocok dan tampak layak konsumsi. Mitra dapat melanjutkan pengambilan setelah memeriksa kondisi produk langsung.'
                : 'Produk masih tampak layak jual. Pertahankan untuk penjualan dan jangan ajukan donasi melalui hasil ini.',
            'acceptable' => 'Periksa produk secara langsung. Jika kondisinya sesuai, lanjutkan proses donasi sesuai alur persetujuan yang berlaku.',
            'poor' => 'Jangan donasikan. Pisahkan produk dan ikuti prosedur pemeriksaan pangan toko.',
            default => 'Hasil belum cukup jelas. Periksa produk secara manual atau ambil foto ulang.',
        };
    }

    /**
     * Evaluate visible food condition from a user-provided image.
     *
     * @return array{donation_eligible: bool, confidence: float, condition: string, reasoning: string, safety_note: string, provider: string}
     */
    public function evaluateDonationPhoto(Product $product, ProductBatch $batch, string $imageData, string $mimeType): array
    {
        $manualReview = [
            'donation_eligible' => false,
            'confidence' => 0.0,
            'condition' => 'unclear',
            'reasoning' => 'Analisis foto tidak tersedia. Periksa kondisi produk secara manual atau coba foto ulang.',
            'safety_note' => 'Foto tidak dapat memastikan keamanan mikrobiologis, bau, maupun kontaminasi makanan.',
            'provider' => 'manual_review',
        ];

        if (! $this->hasNineRouterKey()) {
            return $manualReview;
        }

        $model = (string) config('services.ninerouter.vision_model');
        $baseUrl = rtrim((string) config('services.ninerouter.base_url', 'http://127.0.0.1:20128/v1'), '/');
        $categoryName = $product->category?->name ?? 'General Food';
        $prompt = <<<PROMPT
Klasifikasikan kondisi visual produk ke dalam tepat satu kategori:
- good: masih layak dijual. donation_eligible HARUS false.
- acceptable: secara visual sudah tidak layak dijual karena cacat tampilan/kematangan/kemasan ringan, tetapi masih tampak berpotensi layak konsumsi dan donasi. donation_eligible boleh true hanya bila produk jelas cocok dan confidence tinggi.
- poor: tampak busuk, berjamur, rusak berat, atau ada tanda yang membuatnya tidak layak konsumsi. donation_eligible HARUS false.
- unclear: foto/produk/kondisi tidak jelas atau meragukan. donation_eligible HARUS false.
Jangan memilih acceptable hanya karena produk mendekati kedaluwarsa; tanggal tidak terlihat pada foto dan tidak membuktikan kondisi konsumsi.
Jika foto buram, objek tidak terlihat jelas, jenis makanan tidak dapat dikenali, atau kondisi meragukan, pilih donation_eligible=false dan condition="unclear".
Jangan pernah menyatakan makanan pasti aman. Foto tidak dapat menilai bau, suhu penyimpanan, kontaminasi, atau mikroorganisme. Jika ada keraguan, minta pemeriksaan manual.

Produk pada batch yang dipilih: {$product->name} (SKU {$product->sku}), kategori {$categoryName}, batch {$batch->batch_code}, stok tersedia {$batch->quantity_available} {$product->unit}, kedaluwarsa {$batch->expiration_date->toDateString()}.
Nilai apakah objek pada foto cocok dengan produk yang disebutkan. Jika tidak cocok, condition="unclear" dan donation_eligible=false. Jangan menganggap gambar saja membuktikan makanan aman.

Kembalikan HANYA JSON valid dengan key donation_eligible (boolean), confidence (angka 0 sampai 1), condition (salah satu good, acceptable, poor, unclear), reasoning (alasan singkat dalam Bahasa Indonesia), dan safety_note (batasan keselamatan singkat).
Contoh JSON valid: {"donation_eligible":false,"confidence":0.0,"condition":"unclear","reasoning":"Foto tidak cukup jelas.","safety_note":"Periksa produk secara manual."}
PROMPT;

        try {
            $response = Http::timeout(35)
                ->withToken((string) config('services.ninerouter.api_key'))
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a cautious visual food condition screening assistant. Never claim food is safe based on an image alone. Return only strict JSON.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mimeType.';base64,'.$imageData, 'detail' => 'low']],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('9Router vision evaluation returned an error.', ['status' => $response->status()]);

                return $manualReview;
            }

            $rawText = $response->json('choices.0.message.content');
            if (is_array($rawText)) {
                $rawText = collect($rawText)
                    ->pluck('text')
                    ->filter(fn ($text): bool => is_string($text))
                    ->implode("\n");
            }
            if (! is_string($rawText) || trim($rawText) === '') {
                return $manualReview;
            }

            $decision = $this->decodeVisionDecision($rawText);
            if (! is_array($decision)) {
                return $manualReview;
            }

            $confidence = is_numeric($decision['confidence'] ?? null)
                ? max(0.0, min(1.0, (float) $decision['confidence']))
                : 0.0;
            $condition = in_array($decision['condition'] ?? null, ['good', 'acceptable', 'poor', 'unclear'], true)
                ? $decision['condition']
                : 'unclear';
            // Donation requires the explicit "acceptable" class: visually not saleable,
            // but without visible spoilage. "good" is saleable and must never enable donation.
            $eligible = ($decision['donation_eligible'] ?? false) === true
                && $confidence >= 0.75
                && $condition === 'acceptable';

            return [
                'donation_eligible' => $eligible,
                'confidence' => $confidence,
                'condition' => $condition,
                'reasoning' => is_string($decision['reasoning'] ?? null) ? trim($decision['reasoning']) : $manualReview['reasoning'],
                'safety_note' => is_string($decision['safety_note'] ?? null) ? trim($decision['safety_note']) : $manualReview['safety_note'],
                'provider' => '9router:'.$model,
            ];
        } catch (Throwable $exception) {
            Log::warning('9Router vision evaluation failed.', ['error' => $exception->getMessage()]);

            return $manualReview;
        }
    }

    /**
     * Evaluate a single product using Cloud AI or fallback Mock Engine.
     *
     * @return array{
     *     action: 'discount'|'donate'|'none',
     *     discount_percentage: float|int,
     *     recommended_partner_id: int|null,
     *     recommended_donation_quantity: int,
     *     reasoning: string,
     *     provider: string,
     * }
     */
    public function evaluateProduct(Product $product, ?string $forceProvider = null): array
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $expDate = Carbon::parse($product->expiration_date, 'Asia/Jakarta')->startOfDay();
        $daysRemaining = (int) $today->diffInDays($expDate, false);

        // Force specific provider if requested during live sandbox simulation
        if ($forceProvider === 'mock') {
            return $this->evaluateViaMock($product, $daysRemaining, 'mock');
        }

        if ($forceProvider === 'gemini' && $this->hasGeminiKey()) {
            try {
                $geminiResult = $this->evaluateViaGemini($product, $daysRemaining);
                if ($geminiResult !== null) {
                    return $geminiResult;
                }
            } catch (Throwable $e) {
                Log::warning('Gemini API evaluation failed during forced run, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($forceProvider === 'groq' && $this->hasGroqKey()) {
            try {
                $groqResult = $this->evaluateViaGroq($product, $daysRemaining);
                if ($groqResult !== null) {
                    return $groqResult;
                }
            } catch (Throwable $e) {
                Log::warning('Groq API evaluation failed during forced run, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($forceProvider === '9router' && $this->hasNineRouterKey()) {
            try {
                $routerResult = $this->evaluateViaNineRouter($product, $daysRemaining);
                if ($routerResult !== null) {
                    return $routerResult;
                }
            } catch (Throwable $e) {
                Log::warning('9Router API evaluation failed during forced run, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1. If Mock Mode is explicitly forced, run the local deterministic engine
        if ($this->shouldUseMock()) {
            return $this->evaluateViaMock($product, $daysRemaining);
        }

        // 2. Attempt Google Gemini API first if configured
        if ($this->hasGeminiKey()) {
            try {
                $geminiResult = $this->evaluateViaGemini($product, $daysRemaining);
                if ($geminiResult !== null) {
                    return $geminiResult;
                }
            } catch (Throwable $e) {
                Log::warning('Gemini API evaluation failed, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Attempt Groq API as secondary cloud provider
        if ($this->hasGroqKey()) {
            try {
                $groqResult = $this->evaluateViaGroq($product, $daysRemaining);
                if ($groqResult !== null) {
                    return $groqResult;
                }
            } catch (Throwable $e) {
                Log::warning('Groq API evaluation failed, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Use the locally configured OpenAI-compatible 9Router gateway.
        if ($this->hasNineRouterKey()) {
            try {
                $routerResult = $this->evaluateViaNineRouter($product, $daysRemaining);
                if ($routerResult !== null) {
                    return $routerResult;
                }
            } catch (Throwable $e) {
                Log::warning('9Router API evaluation failed, falling back.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 5. Graceful Fallback: Deterministic Mock Engine
        return $this->evaluateViaMock($product, $daysRemaining, 'mock_fallback');
    }

    /**
     * Evaluate a collection of products.
     *
     * @param  Collection<int, Product>|array<int, Product>  $products
     * @return array<int, array{product: Product, decision: array<string, mixed>}>
     */
    public function evaluateBatch(Collection|array $products): array
    {
        $results = [];

        foreach ($products as $product) {
            $decision = $this->evaluateProduct($product);
            $results[] = [
                'product' => $product,
                'decision' => $decision,
            ];
        }

        return $results;
    }

    /**
     * Local Deterministic Mock Engine.
     *
     * @return array{
     *     action: 'discount'|'donate'|'none',
     *     discount_percentage: float|int,
     *     recommended_partner_id: int|null,
     *     recommended_donation_quantity: int,
     *     reasoning: string,
     *     provider: string,
     * }
     */
    public function evaluateViaMock(Product $product, int $daysRemaining, string $providerName = 'mock'): array
    {
        $partnerId = DonationPartner::query()->where('is_active', true)->value('id');

        if ($daysRemaining <= 2) {
            return [
                'action' => 'donate',
                'discount_percentage' => 0,
                'recommended_partner_id' => $partnerId,
                'recommended_donation_quantity' => max(0, (int) $product->quantity),
                'reasoning' => 'Urgent: Expires in less than 48h. Recommended for donation.',
                'provider' => $providerName,
            ];
        }

        if ($daysRemaining >= 3 && $daysRemaining <= 7) {
            return [
                'action' => 'discount',
                'discount_percentage' => 30,
                'recommended_partner_id' => null,
                'recommended_donation_quantity' => 0,
                'reasoning' => 'Optimal window for clearance sale discount.',
                'provider' => $providerName,
            ];
        }

        return [
            'action' => 'none',
            'discount_percentage' => 0,
            'recommended_partner_id' => null,
            'recommended_donation_quantity' => 0,
            'reasoning' => 'Stock condition safe. Shelf life exceeds clearance window.',
            'provider' => $providerName,
        ];
    }

    /**
     * Query Google Gemini API (Gemini 1.5 Flash).
     */
    protected function evaluateViaGemini(Product $product, int $daysRemaining): ?array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model', 'gemini-1.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $prompt = $this->buildPrompt($product, $daysRemaining);

        $response = Http::timeout(12)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.2,
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Gemini API returned error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $rawText = $response->json('candidates.0.content.parts.0.text');
        if (! $rawText) {
            return null;
        }

        return $this->parseAndValidateJson($rawText, $daysRemaining, 'gemini', (int) $product->quantity);
    }

    /**
     * Query Groq API (Llama 3 / Llama 3.1).
     */
    protected function evaluateViaGroq(Product $product, int $daysRemaining): ?array
    {
        $apiKey = (string) config('services.groq.api_key');
        $model = (string) config('services.groq.model', 'llama-3.1-8b-instant');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $prompt = $this->buildPrompt($product, $daysRemaining);

        $response = Http::timeout(12)
            ->withToken($apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are the SirkulaFood Autonomous Circular Economy Agent. Analyze food inventory items and recommend actions in strict structured JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            Log::warning('Groq API returned error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $rawText = $response->json('choices.0.message.content');
        if (! $rawText) {
            return null;
        }

        return $this->parseAndValidateJson($rawText, $daysRemaining, 'groq', (int) $product->quantity);
    }

    /**
     * Query the local OpenAI-compatible 9Router gateway.
     */
    protected function evaluateViaNineRouter(Product $product, int $daysRemaining): ?array
    {
        $apiKey = (string) config('services.ninerouter.api_key');
        $model = (string) config('services.ninerouter.model', 'openrouter/google/gemma-4-31b-it:free');
        $baseUrl = rtrim((string) config('services.ninerouter.base_url', 'http://127.0.0.1:20128/v1'), '/');
        $prompt = $this->buildPrompt($product, $daysRemaining);

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are the SirkulaFood food-waste prevention evaluator. Return only a JSON object with action, discount_percentage, recommended_partner_id, recommended_donation_quantity, and reasoning.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'stream' => false,
            ]);

        if (! $response->successful()) {
            Log::warning('9Router API returned error response', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $rawText = $response->json('choices.0.message.content');
        if (! is_string($rawText) || trim($rawText) === '') {
            return null;
        }

        return $this->parseAndValidateJson($rawText, $daysRemaining, '9router', (int) $product->quantity);
    }

    /**
     * Build the structured prompt for LLMs.
     */
    protected function buildPrompt(Product $product, int $daysRemaining): string
    {
        $categoryName = $product->category?->name ?? 'General Food';
        $activePartners = DonationPartner::query()
            ->where('is_active', true)
            ->select('id', 'name')
            ->get()
            ->map(fn ($p) => "ID: {$p->id} ({$p->name})")
            ->implode(', ');

        return <<<PROMPT
You are SirkulaFood Agent, an autonomous circular economy AI preventing supermarket food waste.
Analyze this product and output your decision strictly as JSON.

[PRODUCT DETAILS]
- Name: {$product->name}
- SKU: {$product->sku}
- Category: {$categoryName}
- Stock Quantity: {$product->quantity} {$product->unit}
- Original Retail Price: IDR {$product->original_price}
- Current Operational Status: {$product->status}
- Days Remaining Until Expiration: {$daysRemaining} day(s)

[AVAILABLE DONATION PARTNERS]
{$activePartners}

[DECISION RULES]
1. If Days Remaining <= 2: Perishable safety risk. Recommend "donate" to an available partner ID with 0 discount.
2. If 3 <= Days Remaining <= 7: Clearance markdown window. Recommend "discount" with appropriate percentage (between 20 and 50) and recommended_partner_id: null.
3. If Days Remaining > 7: Stock is safe. Recommend "none" with discount_percentage: 0 and recommended_partner_id: null.
For a donation recommendation, set recommended_donation_quantity to an integer from 1 through Stock Quantity; recommend only the amount justified by near expiry and avoid exceeding the batch stock.

[REQUIRED JSON SCHEMA]
Respond ONLY with a JSON object matching this schema without markdown fences:
{
  "action": "discount" | "donate" | "none",
  "discount_percentage": number,
  "recommended_partner_id": number | null,
  "recommended_donation_quantity": integer,
  "reasoning": "string explanation"
}
PROMPT;
    }

    /**
     * Parse and sanitize raw JSON output from LLM.
     */
    protected function parseAndValidateJson(string $rawText, int $daysRemaining, string $provider, int $maximumDonationQuantity): ?array
    {
        try {
            // Remove markdown code fences if present
            $cleanText = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText)) ?? '');
            $data = json_decode($cleanText, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                return null;
            }

            $action = strtolower((string) ($data['action'] ?? 'none'));
            if (! in_array($action, ['discount', 'donate', 'none'], true)) {
                $action = 'none';
            }

            $discountPercentage = isset($data['discount_percentage']) ? (float) $data['discount_percentage'] : 0.0;
            if ($action === 'discount' && ($discountPercentage <= 0 || $discountPercentage > 50)) {
                $discountPercentage = 30.0;
            } elseif ($action !== 'discount') {
                $discountPercentage = 0.0;
            }

            $partnerId = isset($data['recommended_partner_id']) && is_numeric($data['recommended_partner_id'])
                ? (int) $data['recommended_partner_id']
                : null;

            $recommendedDonationQuantity = $action === 'donate'
                ? max(1, min(max(1, $maximumDonationQuantity), (int) ($data['recommended_donation_quantity'] ?? $maximumDonationQuantity)))
                : 0;

            if ($action === 'donate' && ! $partnerId) {
                $partnerId = DonationPartner::query()->where('is_active', true)->value('id');
            }

            if ($action === 'donate' && $partnerId !== null && ! DonationPartner::query()
                ->whereKey($partnerId)
                ->where('is_active', true)
                ->exists()) {
                $partnerId = DonationPartner::query()->where('is_active', true)->value('id');
            }

            $reasoning = ! empty($data['reasoning']) && is_string($data['reasoning'])
                ? trim($data['reasoning'])
                : 'Automated AI circular economy evaluation.';

            return [
                'action' => $action,
                'discount_percentage' => $discountPercentage,
                'recommended_partner_id' => $partnerId,
                'recommended_donation_quantity' => $recommendedDonationQuantity,
                'reasoning' => $reasoning,
                'provider' => $provider,
            ];
        } catch (JsonException $e) {
            Log::warning("Failed to decode {$provider} JSON response", [
                'raw' => $rawText,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if Mock Mode is activated.
     */
    public function shouldUseMock(): bool
    {
        if (config('app.enable_mock_ai', true) === true) {
            return true;
        }

        return ! $this->hasGeminiKey() && ! $this->hasGroqKey() && ! $this->hasNineRouterKey();
    }

    /**
     * Check if Gemini API key is configured.
     */
    public function hasGeminiKey(): bool
    {
        return ! empty(config('services.gemini.api_key'));
    }

    /**
     * Check if Groq API key is configured.
     */
    public function hasGroqKey(): bool
    {
        return ! empty(config('services.groq.api_key'));
    }

    public function hasNineRouterKey(): bool
    {
        return ! empty(config('services.ninerouter.api_key'));
    }
}
