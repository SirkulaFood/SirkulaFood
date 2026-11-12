<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:donation,discount'],
            'period' => ['nullable', 'in:daily,monthly,yearly'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $type = $filters['type'] ?? 'donation';
        $period = $filters['period'] ?? 'daily';
        $start = match ($period) {
            'monthly' => CarbonImmutable::createFromFormat('Y-m', $filters['month'] ?? now()->format('Y-m'))->startOfMonth(),
            'yearly' => CarbonImmutable::createFromDate((int) ($filters['year'] ?? now()->year), 1, 1)->startOfYear(),
            default => CarbonImmutable::createFromFormat('Y-m-d', $filters['date'] ?? now()->toDateString())->startOfDay(),
        };
        $end = match ($period) {
            'monthly' => $start->endOfMonth(),
            'yearly' => $start->endOfYear(),
            default => $start->endOfDay(),
        };

        $query = AgentLog::query()
            ->with(['product:id,name,sku,original_price,discount_price', 'partner:id,name'])
            ->where('status', 'success')
            ->whereBetween('created_at', [$start, $end]);

        if ($type === 'donation') {
            $query->where('action_type', 'donation_requested')
                ->where('payload->event_type', 'donation_completed');
        } else {
            $query->where('action_type', 'discount_applied');
        }

        $records = $query->orderBy('created_at')->get()->map(function (AgentLog $log) use ($type): array {
            $payload = $log->payload ?? [];
            $storedOriginalPrice = (float) ($payload['original_price'] ?? 0);
            $storedDiscountPrice = (float) ($payload['new_discount_price'] ?? $payload['discount_price'] ?? 0);
            $productOriginalPrice = (float) ($log->product?->original_price ?? 0);
            $discountPercentage = (float) ($payload['discount_percentage'] ?? $payload['markdown_pct'] ?? 0);

            if ($discountPercentage <= 0 && $storedOriginalPrice > $storedDiscountPrice && $storedOriginalPrice > 0) {
                $discountPercentage = round((1 - ($storedDiscountPrice / $storedOriginalPrice)) * 100, 1);
            }

            $legacyPricePayload = $productOriginalPrice >= 1000
                && (($storedOriginalPrice > 0 && $storedOriginalPrice < 1000)
                    || ($storedDiscountPrice > 0 && $storedDiscountPrice < 1000));
            $originalPrice = $legacyPricePayload || $storedOriginalPrice <= 0
                ? $productOriginalPrice
                : $storedOriginalPrice;
            if ($legacyPricePayload && $discountPercentage <= 0 && $productOriginalPrice > (float) ($log->product?->discount_price ?? 0)) {
                $discountPercentage = round((1 - ((float) $log->product->discount_price / $productOriginalPrice)) * 100, 1);
            }
            $discountPrice = $legacyPricePayload
                ? ($discountPercentage > 0
                    ? round($originalPrice * (1 - ($discountPercentage / 100)))
                    : (float) ($log->product?->discount_price ?? $originalPrice))
                : ($storedDiscountPrice > 0 ? $storedDiscountPrice : (float) ($log->product?->discount_price ?? 0));

            return [
                'id' => $log->id,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'product_name' => $log->product?->name ?? $payload['product_name'] ?? 'Produk dihapus',
                'sku' => $log->product?->sku ?? $payload['product_sku'] ?? '—',
                'batch_code' => $payload['batch_code'] ?? '—',
                'quantity' => $type === 'donation' ? (int) ($payload['quantity_donated'] ?? $payload['quantity'] ?? 0) : null,
                'unit' => $payload['unit'] ?? null,
                'partner_name' => $log->partner?->name ?? $payload['partner_name'] ?? '—',
                'discount_percentage' => $type === 'discount' ? $discountPercentage : null,
                'original_price' => $type === 'discount' ? $originalPrice : null,
                'discount_price' => $type === 'discount' ? $discountPrice : null,
                'reasoning' => $log->reasoning,
            ];
        })->values();

        $totals = [
            'transactions' => $records->count(),
            'quantity_donated' => $type === 'donation' ? (int) $records->sum('quantity') : 0,
            'average_discount_percentage' => $type === 'discount' && $records->isNotEmpty()
                ? round((float) $records->avg('discount_percentage'), 1)
                : 0,
        ];

        return Inertia::render('Reports/Index', [
            'type' => $type,
            'period' => $period,
            'periodValue' => match ($period) {
                'monthly' => $start->format('Y-m'),
                'yearly' => $start->format('Y'),
                default => $start->format('Y-m-d'),
            },
            'periodStart' => $start->toDateString(),
            'periodEnd' => $end->toDateString(),
            'records' => $records,
            'totals' => $totals,
        ]);
    }
}
