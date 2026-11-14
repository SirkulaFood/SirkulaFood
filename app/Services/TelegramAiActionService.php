<?php

namespace App\Services;

use App\Models\AgentLog;
use App\Models\DonationPartner;
use App\Models\InventorySyncSetting;
use App\Models\Product;
use App\Models\ProductAutomationPolicy;
use App\Models\ProductBatch;
use App\Models\TelegramAiAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramAiActionService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Execute an explicitly authorized, product-scoped donation from an owner or manager. */
    public function executeDirectAutomaticDonation(
        ?int $productId,
        string $productName,
        ?int $quantity,
        ?string $unit,
        User $user,
        string $chatId,
        ?string $telegramMessageId = null
    ): string {
        if (! in_array($user->role, ['business_owner', 'manager'], true)
            || ! filled($user->telegram_chat_id)
            || ! hash_equals((string) $user->telegram_chat_id, $chatId)) {
            throw new AuthorizationException('Only the linked business owner or manager may directly authorize an automatic donation.');
        }

        if (! $productId && trim($productName) === '') {
            return 'Saya belum dapat mengenali nama produk dari pesan itu. Sebutkan nama produk atau SKU yang tampil di Inventory. Kebijakan dan stok tidak berubah.';
        }

        $product = Product::query()
            ->whereIn('status', ['active', 'discounted'])
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->when(! $productId && $productName !== '', function ($query) use ($productName): void {
                $normalizedName = mb_strtolower(trim($productName));
                $query->where(function ($productQuery) use ($normalizedName): void {
                    $productQuery->whereRaw('LOWER(name) = ?', [$normalizedName])
                        ->orWhereRaw('LOWER(sku) = ?', [$normalizedName]);
                });
            })
            ->first();

        if (! $product || ($productName !== ''
            && strcasecmp(trim($productName), (string) $product->name) !== 0
            && strcasecmp(trim($productName), (string) $product->sku) !== 0)) {
            return 'Saya belum dapat mencocokkan produk itu secara persis dengan stok aktif. Sebutkan nama atau SKU yang tampil di Inventory. Kebijakan dan stok tidak berubah.';
        }

        if ($quantity !== null && ($quantity < 1 || ! $this->unitsMatch($unit, (string) $product->unit))) {
            return "Jumlah donasi harus bilangan bulat positif dan satuannya harus {$product->unit}. Kebijakan dan stok tidak berubah.";
        }

        $messageKey = $telegramMessageId !== null ? hash('sha256', $chatId.':'.$telegramMessageId) : null;
        $prepared = DB::transaction(function () use ($product, $quantity, $user, $chatId, $messageKey): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($messageKey !== null) {
                $previousRequest = TelegramAiAction::query()
                    ->where('action_type', 'automation_policy')
                    ->where('payload->source_message_key', $messageKey)
                    ->first();
                if ($previousRequest) {
                    return ['duplicate_reply' => (string) ($previousRequest->result['reply'] ?? 'Perintah donasi ini sudah diterima dan sedang diproses.'), 'action_ids' => []];
                }
            }

            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $hasActiveDonationPartners = DonationPartner::query()->active()->where('partner_type', 'foundation')->exists();
            if (! $hasActiveDonationPartners) {
                return ['error' => 'Belum ada Yayasan/Donation Partner aktif yang dapat menerima donasi. Kebijakan dan stok tidak berubah.', 'action_ids' => []];
            }

            $today = now('Asia/Jakarta')->startOfDay();
            $eligibleThrough = $today->copy()->addDays(2);
            $batches = ProductBatch::query()
                ->where('product_id', $lockedProduct->id)
                ->where('quantity_available', '>', 0)
                ->whereIn('status', ['active', 'discounted'])
                ->whereDate('expiration_date', '>=', $today->toDateString())
                ->whereDate('expiration_date', '<=', $eligibleThrough->toDateString())
                ->orderBy('expiration_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $batchIds = $batches->modelKeys();
            $unreducedDonations = AgentLog::query()
                ->where('product_id', $lockedProduct->id)
                ->where('action_type', 'donation_requested')
                ->where('status', 'success')
                ->where('payload->event_type', 'donation_completed')
                ->get(['payload'])
                ->filter(fn (AgentLog $log): bool => in_array((int) ($log->payload['batch_id'] ?? 0), $batchIds, true)
                    && ($log->payload['stock_reduction_applied'] ?? true) === false)
                ->groupBy(fn (AgentLog $log): int => (int) $log->payload['batch_id'])
                ->map(fn ($logs): int => (int) $logs->sum(fn (AgentLog $log): int => (int) ($log->payload['quantity_donated'] ?? 0)));

            $availableByBatch = $batches->mapWithKeys(fn (ProductBatch $batch): array => [
                $batch->id => max(0, (int) $batch->quantity_available - (int) $unreducedDonations->get($batch->id, 0)),
            ]);
            $availableQuantity = (int) $availableByBatch->sum();
            if ($quantity !== null && $quantity > $availableQuantity) {
                return ['error' => sprintf(
                    'Stok batch %s yang memenuhi syarat 0–2 hari hanya %d %s; permintaan %d %s belum dijalankan. Kebijakan dan stok tidak berubah.',
                    $lockedProduct->name,
                    $availableQuantity,
                    $lockedProduct->unit,
                    $quantity,
                    $lockedProduct->unit
                ), 'action_ids' => []];
            }

            $policy = ProductAutomationPolicy::query()->where('product_id', $lockedProduct->id)->lockForUpdate()->first()
                ?? new ProductAutomationPolicy(['product_id' => $lockedProduct->id]);
            $policy->auto_donation_enabled = true;
            $policy->auto_donation_prealert_enabled = true;
            $policy->updated_by = $user->id;
            $policy->save();

            $scopes = ['donation', 'donation_prealert'];
            $change = [[
                'product_id' => $lockedProduct->id,
                'product_name' => $lockedProduct->name,
                'sku' => $lockedProduct->sku,
                'enabled' => true,
                'scopes' => $scopes,
            ]];
            $scopeDescription = $lockedProduct->name.' ['.$lockedProduct->sku.'] otomatis tanpa konfirmasi per item untuk donasi dan pre-alert donasi besok';
            $policyReply = 'Donasi otomatis tanpa konfirmasi per item aktif untuk '.$lockedProduct->name.'.';
            $policyAction = TelegramAiAction::query()->create([
                'id' => (string) Str::uuid(),
                'telegram_chat_id' => $chatId,
                'requested_by' => $user->id,
                'decided_by' => $user->id,
                'action_type' => 'automation_policy',
                'product_id' => null,
                'payload' => [
                    'enabled' => true,
                    'scopes' => [],
                    'product_changes' => $change,
                    'direct_owner_command' => true,
                    'source_message_key' => $messageKey,
                ],
                'status' => 'processing',
                'expires_at' => now()->addMinutes(10),
            ]);

            AgentLog::query()->create([
                'action_type' => 'manual_override',
                'reasoning' => sprintf('Product donation automation explicitly enabled by %s through a direct Telegram instruction: %s.', $user->name, $scopeDescription),
                'payload' => [
                    'event' => 'product_automation_policy_updated',
                    'scopes' => $scopes,
                    'enabled' => true,
                    'product_changes' => $change,
                    'direct_owner_command' => true,
                    'actor_id' => $user->id,
                    'telegram_chat_id' => $chatId,
                    'telegram_message_key' => $messageKey,
                ],
                'status' => 'success',
            ]);

            $targetQuantity = $quantity ?? $availableQuantity;
            $remainingQuantity = $targetQuantity;
            $donationActions = [];
            foreach ($batches as $batch) {
                $batchAvailable = (int) $availableByBatch->get($batch->id, 0);
                $batchQuantity = min($batchAvailable, $remainingQuantity);
                if ($batchQuantity < 1) {
                    continue;
                }

                TelegramAiAction::query()
                    ->where('action_type', 'donation_batch')
                    ->where('payload->batch_id', $batch->id)
                    ->whereIn('status', ['pending', 'scheduled', 'deferred', 'awaiting_quantity'])
                    ->update(['status' => 'cancelled', 'completed_at' => now()]);

                $donationAction = TelegramAiAction::query()->create([
                    'id' => (string) Str::uuid(),
                    'telegram_chat_id' => $chatId,
                    'requested_by' => $user->id,
                    'decided_by' => $user->id,
                    'action_type' => 'donation_batch',
                    'product_id' => $lockedProduct->id,
                    'payload' => [
                        'batch_id' => $batch->id,
                        'batch_code' => $batch->batch_code,
                        'quantity' => $batchQuantity,
                        'recommended_quantity' => $batchQuantity,
                        'unit' => $lockedProduct->unit,
                        'stock_mode' => 'reduce',
                        'preapproved' => true,
                        'automation_policy' => true,
                        'prealert_auto' => false,
                        'stage' => 'auto_preapproved',
                        'eligible_on' => $today->toDateString(),
                        'notification_date' => $today->toDateString(),
                        'reasoning' => 'Donasi diminta langsung oleh Business Owner/Manager melalui Telegram tanpa konfirmasi per item.',
                        'ai_provider' => 'SirkulaFood_direct_owner_command',
                        'source_message_key' => $messageKey,
                    ],
                    'status' => 'scheduled',
                    'expires_at' => Carbon::parse($batch->expiration_date, 'Asia/Jakarta')->endOfDay(),
                ]);
                $donationActions[] = $donationAction->id;
                $remainingQuantity -= $batchQuantity;
                if ($remainingQuantity < 1) {
                    break;
                }
            }

            $policyAction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => ['enabled' => true, 'scopes' => $scopes, 'product_changes' => $change, 'reply' => $policyReply],
            ]);

            return [
                'policy_action_id' => $policyAction->id,
                'product_name' => $lockedProduct->name,
                'unit' => $lockedProduct->unit,
                'requested_quantity' => $targetQuantity,
                'action_ids' => $donationActions,
                'eligible_quantity' => $availableQuantity,
                'message_key' => $messageKey,
            ];
        });

        if (isset($prepared['error'])) {
            return $prepared['error'];
        }
        if (isset($prepared['duplicate_reply'])) {
            return $prepared['duplicate_reply'];
        }

        foreach ($prepared['action_ids'] as $actionId) {
            $this->completeScheduledDonation($actionId, false);
        }

        $reply = sprintf(
            'Donasi otomatis tanpa konfirmasi per item aktif untuk %s; pre-alert donasi besok juga aktif.',
            $prepared['product_name']
        );
        if ($prepared['action_ids'] === []) {
            $reply .= ' Belum ada batch produk ini yang kedaluwarsa dalam 0–2 hari, jadi stok belum berubah. Batch yang memenuhi syarat berikutnya akan diproses otomatis.';
        } else {
            $completedActions = TelegramAiAction::query()
                ->whereIn('id', $prepared['action_ids'])
                ->where('status', 'completed')
                ->get(['result']);
            $donatedQuantity = (int) $completedActions->sum(fn (TelegramAiAction $action): int => (int) ($action->result['quantity'] ?? 0));
            $reply .= sprintf(
                ' Donasi sebanyak %d dari %d %s diproses dari batch terdekat kedaluwarsa; stok dikurangi dan notifikasi dikirim ke semua Yayasan aktif.',
                $donatedQuantity,
                (int) $prepared['requested_quantity'],
                $prepared['unit']
            );
            if ($donatedQuantity < (int) $prepared['requested_quantity']) {
                $reply .= ' Batch yang berubah atau gagal diproses tidak mengurangi stok; kebijakan otomatis tetap aktif untuk batch berikutnya yang memenuhi syarat.';
            }
        }

        TelegramAiAction::query()->whereKey($prepared['policy_action_id'])->update([
            'result' => [
                'enabled' => true,
                'scopes' => ['donation', 'donation_prealert'],
                'product_id' => $product->id,
                'donation_action_ids' => $prepared['action_ids'],
                'reply' => $reply,
            ],
        ]);

        return $reply;
    }

    /** @return array{text: string, reply_markup: array<string, mixed>|null} */
    public function decideAutomationPolicy(string $actionId, User $user, string $chatId, string $decision): array
    {
        if (! in_array($user->role, ['business_owner', 'manager'], true)) {
            throw new AuthorizationException('Only a business owner or manager may change automatic inventory policies.');
        }
        if (! in_array($decision, ['arm', 'activate', 'cancel'], true)) {
            throw new AuthorizationException('Unknown automation policy confirmation.');
        }

        return DB::transaction(function () use ($actionId, $user, $chatId, $decision): array {
            $action = TelegramAiAction::query()->lockForUpdate()->findOrFail($actionId);
            if ($action->action_type !== 'automation_policy'
                || (int) $action->requested_by !== (int) $user->id
                || ! hash_equals($action->telegram_chat_id, $chatId)) {
                throw new AuthorizationException('This automatic policy request belongs to another manager chat.');
            }

            if ($decision === 'cancel') {
                if (! in_array($action->status, ['pending', 'cooldown'], true)) {
                    return ['text' => 'Permintaan otomatisasi ini sudah diproses.', 'reply_markup' => null];
                }
                $action->update(['status' => 'cancelled', 'decided_by' => $user->id, 'completed_at' => now()]);

                return ['text' => 'Aktivasi otomatisasi dibatalkan. Tidak ada kebijakan yang berubah.', 'reply_markup' => null];
            }

            if ($action->expires_at?->isPast()) {
                $action->update(['status' => 'expired', 'completed_at' => now()]);

                return ['text' => 'Permintaan aktivasi kedaluwarsa. Tidak ada kebijakan yang berubah; ajukan permintaan baru bila masih diperlukan.', 'reply_markup' => null];
            }

            $payload = $action->payload ?? [];
            // `arm` is retained for buttons sent by older bot messages. Both the
            // old and new activation buttons now perform one explicit confirmation.
            if (! in_array($action->status, ['pending', 'cooldown'], true)) {
                return ['text' => 'Permintaan otomatisasi ini sudah diproses atau tidak lagi aktif. Ajukan permintaan baru bila masih diperlukan.', 'reply_markup' => null];
            }

            $settings = InventorySyncSetting::query()->lockForUpdate()->findOrFail(1);
            $fields = [
                'donation' => 'auto_donation_enabled',
                'discount' => 'auto_discount_enabled',
                'donation_prealert' => 'auto_donation_prealert_enabled',
            ];
            $enabled = (bool) ($payload['enabled'] ?? false);
            $scopes = array_values(array_intersect((array) ($payload['scopes'] ?? []), array_keys($fields)));
            $productChanges = [];
            foreach ((array) ($payload['product_changes'] ?? []) as $change) {
                $productId = (int) ($change['product_id'] ?? 0);
                $productScopes = array_values(array_intersect((array) ($change['scopes'] ?? []), array_keys($fields)));
                if ($productId < 1 || ! is_bool($change['enabled'] ?? null) || $productScopes === []) {
                    throw new AuthorizationException('A product automation change is incomplete. No strategy was applied.');
                }
                $product = Product::query()->findOrFail($productId);
                $policy = ProductAutomationPolicy::query()->lockForUpdate()->firstOrNew(['product_id' => $productId]);
                foreach ($productScopes as $scope) {
                    $policy->{$fields[$scope]} = $change['enabled'];
                }
                $policy->updated_by = $user->id;
                $policy->save();
                $productChanges[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'enabled' => $change['enabled'],
                    'scopes' => $productScopes,
                ];
            }

            if ($productChanges === []) {
                if ($scopes === []) {
                    throw new AuthorizationException('No valid automatic policy scope was requested.');
                }
                foreach ($scopes as $scope) {
                    $settings->{$fields[$scope]} = $enabled;
                }
                $anyAutomationRemainsEnabled = $settings->auto_donation_enabled
                    || $settings->auto_discount_enabled
                    || $settings->auto_donation_prealert_enabled;
                if ($enabled) {
                    $settings->auto_decision_activated_by = $user->id;
                    $settings->auto_decision_activated_at = now();
                } elseif (! $anyAutomationRemainsEnabled) {
                    $settings->auto_decision_activated_by = null;
                    $settings->auto_decision_activated_at = null;
                }
                $settings->save();
            }

            $labelForScope = fn (string $scope): string => match ($scope) {
                'donation' => 'donasi otomatis 0–2 hari',
                'discount' => 'diskon AI',
                'donation_prealert' => 'pre-alert donasi besok',
            };
            $scopeDescription = $productChanges !== []
                ? collect($productChanges)->map(fn (array $change): string => sprintf(
                    '%s [%s] %s: %s',
                    $change['product_name'],
                    $change['sku'],
                    $change['enabled'] ? 'otomatis tanpa konfirmasi item' : 'kembali perlu konfirmasi',
                    collect($change['scopes'])->map($labelForScope)->implode(', ')
                ))->implode('; ')
                : collect($scopes)->map($labelForScope)->implode(', ');

            AgentLog::query()->create([
                // Keep action_type within the existing database enum. The specific
                // event and affected scopes are recorded in the JSON payload.
                'action_type' => 'manual_override',
                'reasoning' => sprintf('Inventory automation strategy updated by %s through Telegram confirmation: %s.', $user->name, $scopeDescription),
                'payload' => [
                    'event' => $productChanges !== [] ? 'product_automation_policy_updated' : ($enabled ? 'automation_policy_enabled' : 'automation_policy_disabled'),
                    'scopes' => $scopes,
                    'enabled' => $enabled,
                    'product_changes' => $productChanges,
                    'actor_id' => $user->id,
                    'telegram_chat_id' => $chatId,
                ],
                'status' => 'success',
            ]);

            $action->update([
                'status' => 'completed',
                'decided_by' => $user->id,
                'completed_at' => now(),
                'result' => ['enabled' => $enabled, 'scopes' => $scopes, 'product_changes' => $productChanges],
            ]);

            return [
                'text' => sprintf('Strategi otomatis berhasil diperbarui: %s. Dikonfirmasi oleh %s.', $scopeDescription, $user->name),
                'reply_markup' => null,
            ];
        });
    }

    public function decide(string $actionId, User $user, string $chatId, string $decision): string
    {
        if (! in_array($user->role, ['business_owner', 'manager', 'cashier'], true)) {
            throw new AuthorizationException('Only a business owner, manager, or cashier may confirm inventory actions.');
        }

        if (! in_array($decision, ['confirm', 'cancel', 'reduce', 'keep'], true)) {
            throw new AuthorizationException('Unknown Telegram AI action decision.');
        }

        return DB::transaction(function () use ($actionId, $user, $chatId, $decision): string {
            $action = TelegramAiAction::query()->lockForUpdate()->findOrFail($actionId);
            if ((int) $action->requested_by !== (int) $user->id || ! hash_equals($action->telegram_chat_id, $chatId)) {
                throw new AuthorizationException('This pending action belongs to another manager chat.');
            }

            if ($action->status !== 'pending') {
                return 'Permintaan ini sudah diproses dengan status: '.$action->status.'.';
            }

            if ($action->expires_at->isPast()) {
                $action->update(['status' => 'expired', 'completed_at' => now()]);

                return 'Konfirmasi kedaluwarsa. Belum ada perubahan stok atau harga; silakan ajukan ulang jika masih diperlukan.';
            }

            if ($decision === 'cancel') {
                $action->update([
                    'status' => 'cancelled',
                    'decided_by' => $user->id,
                    'completed_at' => now(),
                ]);

                return 'Permintaan dibatalkan. Stok dan harga tidak berubah.';
            }

            $payload = $action->payload ?? [];
            if ($action->action_type === 'donate') {
                $deductStock = $decision !== 'keep';
                $result = app(InventoryService::class)->donateExpiringProductQuantity(
                    (int) $action->product_id,
                    (int) $payload['quantity'],
                    $user->id,
                    $action->id,
                    $deductStock
                );

                if (! $result['success']) {
                    $action->update([
                        'status' => 'failed',
                        'decided_by' => $user->id,
                        'completed_at' => now(),
                        'result' => $result,
                    ]);

                    return sprintf(
                        'Stok berubah sebelum konfirmasi. Donasi tidak dijalankan. Stok %s yang masih tersedia pada batch kedaluwarsa maksimal 7 hari: %d %s.',
                        $result['product_name'],
                        $result['available_quantity'],
                        $result['unit']
                    );
                }

                $action->update([
                    'status' => 'completed',
                    'decided_by' => $user->id,
                    'completed_at' => now(),
                    'result' => [
                        'quantity' => $result['quantity'],
                        'unit' => $result['unit'],
                        'remaining_stock' => $result['remaining_stock'],
                        'allocations' => $result['allocations'],
                        'notifications_queued' => $result['notifications_queued'],
                        'partner_count' => $result['partner_count'],
                        'stock_reduction_applied' => $deductStock,
                    ],
                ]);

                $notificationStatus = $result['notifications_queued']
                    ? "Notifikasi email/Telegram dimasukkan ke antrean untuk {$result['partner_count']} yayasan aktif."
                    : 'Donasi tercatat, tetapi antrean notifikasi perlu diperiksa oleh admin.';

                return sprintf(
                    'Donasi dikonfirmasi: %s %d %s. Stok %s; jumlah tercatat %d %s. Batch dengan tanggal kedaluwarsa terdekat digunakan lebih dahulu. %s',
                    $result['product_name'],
                    $result['quantity'],
                    $result['unit'],
                    $deductStock ? 'dikurangi' : 'tidak dikurangi',
                    $result['remaining_stock'],
                    $result['unit'],
                    $notificationStatus
                );
            }

            if ($decision !== 'confirm' && $decision !== 'cancel') {
                throw new AuthorizationException('Choose the normal confirmation button for non-donation requests.');
            }

            if ($action->action_type === 'discount') {
                $result = app(InventoryService::class)->requestExpiringDiscountApproval(
                    (int) $action->product_id,
                    (int) $payload['discount_percentage'],
                    (string) $payload['reason']
                );

                if ($result['batch_count'] === 0) {
                    $action->update([
                        'status' => 'failed',
                        'decided_by' => $user->id,
                        'completed_at' => now(),
                        'result' => $result,
                    ]);

                    return 'Batch aktif yang kedaluwarsa dalam 7 hari sudah tidak tersedia. Harga tidak berubah.';
                }

                $action->update([
                    'status' => 'completed',
                    'decided_by' => $user->id,
                    'completed_at' => now(),
                    'result' => $result,
                ]);

                return sprintf(
                    'Rekomendasi diskon %d%% untuk %s diajukan pada %d batch mendekati kedaluwarsa. Harga belum berubah; manager/kasir perlu menyetujui melalui notifikasi Telegram atau menu Discount Recommendations.',
                    $payload['discount_percentage'],
                    $result['product_name'],
                    $result['batch_count']
                );
            }

            throw new AuthorizationException('Unsupported SirkulaFood AI action.');
        });
    }

    public function decideDonationBatch(string $actionId, User $user, string $chatId, string $decision): string
    {
        if (! in_array($decision, ['reject', 'defer', 'custom', 'reduce', 'keep'], true)) {
            throw new AuthorizationException('Unknown donation decision.');
        }
        if ($decision === 'defer'
            && ($this->authorizedDonationAction($actionId, $user, $chatId)->payload['stage'] ?? null) !== 'prealert') {
            throw new AuthorizationException('Only a next-day donation pre-alert can be deferred.');
        }
        $action = $this->claimDonationAction($actionId, $user, $chatId, ['pending']);
        if (! $action) {
            return 'Permintaan donasi ini sudah diproses atau sedang diproses oleh manager lain.';
        }

        $payload = $action->payload ?? [];
        if ($decision === 'reject') {
            $action->update(['status' => 'cancelled', 'decided_by' => $user->id, 'completed_at' => now()]);
            $this->cancelSiblingDonationActions($action);

            return 'Batch ini ditandai tidak untuk didonasikan. Stok tidak berubah.';
        }

        if ($decision === 'defer') {
            $action->update(['status' => 'deferred', 'decided_by' => $user->id]);

            return 'Baik. SirkulaFood akan mengirim ulang konfirmasi saat batch masuk ambang donasi besok. Belum ada stok yang diubah.';
        }

        if ($decision === 'custom') {
            $action->update(['status' => 'awaiting_quantity', 'decided_by' => $user->id]);

            return sprintf(
                'Kirim jumlah dan pilihan stok dengan format: /donasi %s JUMLAH kurangi atau /donasi %s JUMLAH tetap. Stok belum berubah.',
                $action->id,
                $action->id
            );
        }

        if (! in_array($decision, ['reduce', 'keep'], true)) {
            throw new AuthorizationException('Unknown donation decision.');
        }

        $quantity = (int) ($payload['recommended_quantity'] ?? 0);
        $payload['quantity'] = $quantity;
        $payload['stock_mode'] = $decision;
        $payload['preapproved'] = true;
        $futureEligibility = Carbon::parse((string) ($payload['eligible_on'] ?? 'today'), 'Asia/Jakarta')->startOfDay()->isFuture();

        if ($futureEligibility) {
            $action->update([
                'status' => 'scheduled',
                'decided_by' => $user->id,
                'payload' => $payload,
            ]);

            return sprintf(
                'Donasi %s sebanyak %d %s disetujui untuk besok. SirkulaFood akan menjalankannya saat batch masuk ambang tanpa meminta konfirmasi lagi; stok %s.',
                $action->product?->name ?? 'produk',
                $quantity,
                $payload['unit'] ?? 'unit',
                $decision === 'reduce' ? 'akan dikurangi' : 'akan tetap'
            );
        }

        return $this->completeDonation($action, $user->id, $quantity, $decision === 'reduce', false);
    }

    public function submitCustomDonation(string $actionId, User $user, string $chatId, int $quantity, string $stockMode): string
    {
        if (! in_array($stockMode, ['kurangi', 'tetap'], true)) {
            throw new AuthorizationException('Pilih mode stok kurangi atau tetap.');
        }

        $action = $this->claimDonationAction($actionId, $user, $chatId, ['awaiting_quantity']);
        if (! $action) {
            return 'Permintaan jumlah ini sudah ditutup atau sedang diproses.';
        }

        $payload = $action->payload ?? [];
        $batch = ProductBatch::query()->find((int) ($payload['batch_id'] ?? 0));
        if (! $batch || $quantity < 1 || $quantity > (int) $batch->quantity_available) {
            $action->update(['status' => 'awaiting_quantity']);

            return sprintf('Jumlah harus antara 1 dan %d %s yang tercatat pada batch ini.', (int) ($batch?->quantity_available ?? 0), $payload['unit'] ?? 'unit');
        }

        $payload['quantity'] = $quantity;
        $payload['stock_mode'] = $stockMode === 'kurangi' ? 'reduce' : 'keep';
        $payload['preapproved'] = true;
        $futureEligibility = Carbon::parse((string) ($payload['eligible_on'] ?? 'today'), 'Asia/Jakarta')->startOfDay()->isFuture();
        if ($futureEligibility) {
            $action->update(['status' => 'scheduled', 'decided_by' => $user->id, 'payload' => $payload]);

            return sprintf(
                'Donasi %s sebanyak %d %s disetujui untuk besok. Akan ada proses otomatis tanpa konfirmasi ulang; stok %s.',
                $action->product?->name ?? 'produk',
                $quantity,
                $payload['unit'] ?? 'unit',
                $payload['stock_mode'] === 'reduce' ? 'akan dikurangi' : 'akan tetap'
            );
        }

        return $this->completeDonation($action, $user->id, $quantity, $payload['stock_mode'] === 'reduce', false);
    }

    /** Execute a pre-approved donation after it enters the eligibility window. */
    public function completeScheduledDonation(string $actionId, bool $notifyActor = true): ?string
    {
        $action = DB::transaction(function () use ($actionId): ?TelegramAiAction {
            $action = TelegramAiAction::query()->lockForUpdate()->find($actionId);
            if (! $action || $action->action_type !== 'donation_batch' || $action->status !== 'scheduled') {
                return null;
            }
            $payload = $action->payload ?? [];
            $eligibleOn = Carbon::parse((string) ($payload['eligible_on'] ?? '1970-01-01'), 'Asia/Jakarta')->startOfDay();
            if ($eligibleOn->gt(now('Asia/Jakarta')->startOfDay())) {
                return null;
            }
            $settings = InventorySyncSetting::query()->findOrFail(1);
            $productPolicy = app(ProductAutomationPolicyService::class)->forProduct((int) $action->product_id, $settings);
            $automaticScopeEnabled = ($payload['prealert_auto'] ?? false)
                ? ($productPolicy['auto_donation_prealert_enabled'] || $productPolicy['auto_donation_enabled'])
                : $productPolicy['auto_donation_enabled'];
            if (($payload['automation_policy'] ?? false) === true && ! $automaticScopeEnabled) {
                $payload['stage'] = 'eligible';
                $payload['automation_policy'] = false;
                $payload['preapproved'] = false;
                $payload['prealert_auto'] = false;
                $action->update(['status' => 'pending', 'payload' => $payload]);

                return $action->fresh();
            }
            $action->update(['status' => 'processing']);

            return $action->fresh();
        });
        if (! $action) {
            return null;
        }
        if ($action->status === 'pending') {
            $this->notifications->queueDonationApprovalBatch([$action]);

            return 'Batch tidak lagi berada di kebijakan otomatis; permintaan dikembalikan ke jalur persetujuan.';
        }

        // Re-read the payload from the claimed action. The transaction closure
        // above owns its local `$payload`; referring to it here yielded quantity 0.
        $payload = $action->payload ?? [];
        $quantity = (int) ($payload['quantity'] ?? $payload['recommended_quantity'] ?? 0);
        if ($quantity < 1) {
            $message = 'Jadwal donasi otomatis tidak memiliki jumlah yang valid; stok tidak diubah. Buat ulang jadwal setelah memeriksa batch.';
            $action->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => ['success' => false, 'reason' => 'scheduled_donation_quantity_missing'],
            ]);
            Log::error('Scheduled SirkulaFood donation has no valid quantity.', [
                'action_id' => $action->id,
                'product_id' => $action->product_id,
            ]);
            if ($notifyActor) {
                $this->notifications->queueTelegramActionResult($action->telegram_chat_id, $message, 'donation-result:'.$action->id);
            }

            return $message;
        }

        $managerId = (int) ($action->decided_by ?? $action->requested_by);
        $result = $this->completeDonation(
            $action,
            $managerId,
            $quantity,
            ($payload['stock_mode'] ?? 'reduce') === 'reduce',
            true
        );
        if ($notifyActor) {
            $this->notifications->queueTelegramActionResult($action->telegram_chat_id, $result, 'donation-result:'.$action->id);
        }

        return $result;
    }

    private function authorizedDonationAction(string $actionId, User $user, string $chatId): TelegramAiAction
    {
        if (! in_array($user->role, ['business_owner', 'manager', 'cashier'], true)) {
            throw new AuthorizationException('Only a business owner, manager, or cashier may confirm donations.');
        }

        $action = TelegramAiAction::query()->findOrFail($actionId);
        if ($action->action_type !== 'donation_batch'
            || (int) $action->requested_by !== (int) $user->id
            || ! hash_equals($action->telegram_chat_id, $chatId)) {
            throw new AuthorizationException('This donation request belongs to another manager Telegram account.');
        }

        return $action;
    }

    /** Atomically claim a manager action so Telegram retries or double taps cannot execute it twice. */
    private function claimDonationAction(string $actionId, User $user, string $chatId, array $allowedStatuses): ?TelegramAiAction
    {
        return DB::transaction(function () use ($actionId, $user, $chatId, $allowedStatuses): ?TelegramAiAction {
            $action = TelegramAiAction::query()->lockForUpdate()->findOrFail($actionId);
            if ($action->action_type !== 'donation_batch'
                || (int) $action->requested_by !== (int) $user->id
                || ! hash_equals($action->telegram_chat_id, $chatId)) {
                throw new AuthorizationException('This donation request belongs to another manager Telegram account.');
            }
            if (! in_array($action->status, $allowedStatuses, true)) {
                return null;
            }
            if ($action->expires_at->isPast()) {
                $action->update(['status' => 'expired', 'completed_at' => now()]);

                return null;
            }
            $action->update(['status' => 'processing']);

            return $action->fresh();
        });
    }

    private function completeDonation(TelegramAiAction $action, int $managerId, int $quantity, bool $deductStock, bool $scheduled): string
    {
        $payload = $action->payload ?? [];
        $result = app(InventoryService::class)->donateExpiringBatchQuantity(
            (int) ($payload['batch_id'] ?? 0),
            $quantity,
            $managerId,
            $action->id,
            $deductStock,
            ($payload['automation_policy'] ?? false) === true
        );

        if (! $result['success']) {
            $action->update([
                'status' => 'failed',
                'decided_by' => $managerId,
                'completed_at' => now(),
                'result' => $result,
            ]);

            return sprintf(
                'Donasi %s tidak dijalankan karena stok atau tanggal batch berubah. Tersedia untuk donasi: %d %s. Tidak ada stok baru yang dikurangi.',
                $result['product_name'] ?? ($action->product?->name ?? 'produk'),
                (int) ($result['available_quantity'] ?? 0),
                $result['unit'] ?? ($payload['unit'] ?? 'unit')
            );
        }

        $action->update([
            'status' => 'completed',
            'decided_by' => $managerId,
            'completed_at' => now(),
            'result' => [
                'quantity' => $result['quantity'],
                'unit' => $result['unit'],
                'remaining_stock' => $result['remaining_stock'],
                'stock_reduction_applied' => $deductStock,
                'notifications_queued' => $result['notifications_queued'],
                'partner_count' => $result['partner_count'],
                'scheduled' => $scheduled,
            ],
        ]);
        $this->cancelSiblingDonationActions($action->fresh());

        $sourceLabel = ($payload['automation_policy'] ?? false) === true ? 'Donasi otomatis selesai' : 'Donasi dikonfirmasi';

        return sprintf(
            '%s: %s %d %s. Stok %s; tersisa %d %s. Pemberitahuan %s kepada %d yayasan aktif.',
            $sourceLabel,
            $result['product']['name'],
            $quantity,
            $result['unit'],
            $deductStock ? 'dikurangi' : 'tidak dikurangi',
            $result['remaining_stock'],
            $result['unit'],
            $result['notifications_queued'] ? 'diantrekan' : 'perlu diperiksa',
            $result['partner_count']
        );
    }

    private function unitsMatch(?string $requestedUnit, string $productUnit): bool
    {
        if ($requestedUnit === null || trim($requestedUnit) === '') {
            return false;
        }

        $normalize = static function (string $unit): string {
            $unit = mb_strtolower(trim($unit));
            $unit = preg_replace('/[^a-z0-9]/', '', $unit) ?? '';

            return match ($unit) {
                'kilo', 'kilos', 'kilogram', 'kilograms' => 'kg',
                'gr', 'gram', 'grams' => 'g',
                'pc', 'piece', 'pieces', 'unit', 'units' => 'pcs',
                'liter', 'liters', 'litre', 'litres' => 'l',
                'milliliter', 'milliliters', 'millilitre', 'millilitres' => 'ml',
                default => $unit,
            };
        };

        return $normalize($requestedUnit) === $normalize($productUnit);
    }

    private function cancelSiblingDonationActions(TelegramAiAction $action): void
    {
        $batchId = (int) ($action->payload['batch_id'] ?? 0);
        if ($batchId === 0) {
            return;
        }

        TelegramAiAction::query()
            ->where('action_type', 'donation_batch')
            ->whereKeyNot($action->id)
            ->where('payload->batch_id', $batchId)
            ->whereIn('status', ['pending', 'scheduled', 'deferred', 'awaiting_quantity'])
            ->update(['status' => 'cancelled', 'completed_at' => now()]);
    }
}
