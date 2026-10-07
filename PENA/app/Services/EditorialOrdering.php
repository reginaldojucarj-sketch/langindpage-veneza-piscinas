<?php

namespace App\Services;

use App\Exceptions\EditorialUnavailable;
use App\Exceptions\OrderConflict;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class EditorialOrdering
{
    private const STATE_TABLE = 'pena_editorial_state';

    private const ORDER_TABLE = 'pena_post_order';

    private const AUDIT_TABLE = 'pena_editorial_audit';

    public function isAvailable(): bool
    {
        try {
            $this->assertStorageAvailable();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function revision(): ?int
    {
        if (! Schema::hasTable(self::STATE_TABLE)) {
            return null;
        }

        $revision = DB::table(self::STATE_TABLE)->where('id', 1)->value('revision');

        return $revision === null ? null : (int) $revision;
    }

    /**
     * Save only the auxiliary InnoDB ordering data. It deliberately never writes
     * to MyISAM legacy tables, which are not protected by Laravel transactions.
     *
     * @param  list<int>  $submittedIds
     * @return array{revision:int,count:int,replayed:bool}
     */
    public function save(
        array $submittedIds,
        int $expectedRevision,
        int $actorId,
        string $idempotencyKey,
        string $correlationId,
        ?Closure $faultInjector = null,
    ): array {
        $payloadHash = hash('sha256', implode(',', $submittedIds));

        try {
            $this->assertStorageAvailable();

            return DB::transaction(function () use (
                $submittedIds, $expectedRevision, $actorId, $idempotencyKey,
                $correlationId, $payloadHash, $faultInjector,
            ): array {
                $state = DB::table(self::STATE_TABLE)->where('id', 1)->lockForUpdate()->first();
                if ($state === null) {
                    throw new EditorialUnavailable('Editorial ordering state is missing.');
                }

                $prior = DB::table(self::AUDIT_TABLE)
                    ->where('idempotency_key', $idempotencyKey)->first();
                if ($prior !== null) {
                    if ((int) $prior->actor_id !== $actorId || ! hash_equals((string) $prior->payload_hash, $payloadHash)) {
                        throw new OrderConflict('The idempotency key was already used for another order.', 'idempotency_key_reused');
                    }

                    if ($prior->outcome !== 'success') {
                        throw new OrderConflict('The previous request did not complete successfully.', 'previous_request_failed');
                    }

                    return [
                        'revision' => (int) $prior->revision_after,
                        'count' => count($submittedIds),
                        'replayed' => true,
                    ];
                }

                $currentRevision = (int) $state->revision;
                if ($expectedRevision !== $currentRevision) {
                    throw new OrderConflict('The editorial order was changed by another administrator.', 'revision_conflict');
                }

                $publishedIds = DB::table('POST_pena')->where('STATUS_POST', 'PP')
                    ->pluck('ID_POST')->map(fn ($id) => (int) $id)->all();
                $expectedIds = $publishedIds;
                $providedIds = $submittedIds;
                sort($expectedIds, SORT_NUMERIC);
                sort($providedIds, SORT_NUMERIC);
                if ($expectedIds !== $providedIds) {
                    throw new OrderConflict('The published articles changed while this order was open. Reload before saving.', 'published_set_changed');
                }

                $publishedDigest = self::digest($publishedIds);
                $this->checkpoint($faultInjector, 'after_published_set_check');

                DB::table(self::ORDER_TABLE)->delete();
                $this->checkpoint($faultInjector, 'after_order_delete');

                $now = now();
                $orderRows = [];
                foreach ($submittedIds as $index => $postId) {
                    $orderRows[] = [
                        'post_id' => $postId,
                        'sort_order' => $index + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($orderRows, 250) as $chunk) {
                    DB::table(self::ORDER_TABLE)->insert($chunk);
                }
                $this->checkpoint($faultInjector, 'after_order_insert');

                $newRevision = $currentRevision + 1;
                DB::table(self::STATE_TABLE)->where('id', 1)->update([
                    'revision' => $newRevision,
                    'published_digest' => $publishedDigest,
                    'updated_at' => $now,
                ]);
                $this->checkpoint($faultInjector, 'after_state_update');
                $this->checkpoint($faultInjector, 'before_success_audit');

                DB::table(self::AUDIT_TABLE)->insert([
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey,
                    'actor_id' => $actorId,
                    'action' => 'posts.reorder',
                    'entity_type' => 'published_posts',
                    'entity_id' => null,
                    'outcome' => 'success',
                    'revision_before' => $currentRevision,
                    'revision_after' => $newRevision,
                    'payload_hash' => $payloadHash,
                    'summary' => json_encode(['post_count' => count($submittedIds)], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
                $this->checkpoint($faultInjector, 'after_success_audit');

                return ['revision' => $newRevision, 'count' => count($submittedIds), 'replayed' => false];
            }, 3);
        } catch (Throwable $error) {
            $this->recordFailure($actorId, $correlationId, $payloadHash, $error);

            throw $error;
        }
    }

    /**
     * Return a complete, current manual order, otherwise null so readers use the
     * stable legacy date/ID ordering. The legacy set is read-only and may change
     * outside this transaction; the digest detects publication-set drift.
     *
     * @param  list<int>  $publishedIds
     * @return array<int, int>|null post ID => zero-based rank
     */
    public function currentOrder(array $publishedIds): ?array
    {
        return $this->currentOrderSnapshot($publishedIds)['rank'];
    }

    /**
     * Read the manual rank and its revision together, so a form never pairs an
     * older order with a newer revision if another administrator saves midway.
     *
     * @param  list<int>  $publishedIds
     * @return array{rank:array<int,int>|null,revision:int|null}
     */
    public function currentOrderSnapshot(array $publishedIds): array
    {
        if (! Schema::hasTable(self::STATE_TABLE)) {
            return ['rank' => null, 'revision' => null];
        }

        $state = DB::table(self::STATE_TABLE)->where('id', 1)->first(['revision', 'published_digest']);
        if ($state === null) {
            return ['rank' => null, 'revision' => null];
        }

        $revision = (int) $state->revision;
        $unavailableRank = ['rank' => null, 'revision' => $revision];
        if ($publishedIds === [] || ! Schema::hasTable(self::ORDER_TABLE) ||
            ! hash_equals((string) $state->published_digest, self::digest($publishedIds))) {
            return $unavailableRank;
        }

        $rows = DB::table(self::ORDER_TABLE)->orderBy('sort_order')->orderBy('post_id')->get(['post_id', 'sort_order']);
        if ($rows->count() !== count($publishedIds)) {
            return $unavailableRank;
        }

        $orderedIds = $rows->map(fn ($row) => (int) $row->post_id)->all();
        $expectedIds = array_map('intval', $publishedIds);
        sort($orderedIds, SORT_NUMERIC);
        sort($expectedIds, SORT_NUMERIC);
        if ($orderedIds !== $expectedIds) {
            return $unavailableRank;
        }

        $rank = [];
        foreach ($rows as $index => $row) {
            if ((int) $row->sort_order !== $index + 1) {
                return $unavailableRank;
            }
            $rank[(int) $row->post_id] = $index;
        }

        return ['rank' => $rank, 'revision' => $revision];
    }

    public static function digest(array $postIds): string
    {
        $postIds = array_map('intval', $postIds);
        sort($postIds, SORT_NUMERIC);

        return hash('sha256', implode(',', $postIds));
    }

    private function assertStorageAvailable(): void
    {
        foreach ([self::STATE_TABLE, self::ORDER_TABLE, self::AUDIT_TABLE, 'POST_pena'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EditorialUnavailable('Editorial ordering storage is not installed.');
            }
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ([self::STATE_TABLE, self::ORDER_TABLE, self::AUDIT_TABLE] as $table) {
                $engine = DB::selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                )?->engine;
                if (strtolower((string) $engine) !== 'innodb') {
                    throw new EditorialUnavailable('Editorial ordering tables must use InnoDB.');
                }
            }
        }
    }

    private function checkpoint(?Closure $faultInjector, string $name): void
    {
        if ($faultInjector !== null) {
            $faultInjector($name);
        }
    }

    private function recordFailure(int $actorId, string $correlationId, string $payloadHash, Throwable $error): void
    {
        try {
            if (! Schema::hasTable(self::AUDIT_TABLE)) {
                throw new EditorialUnavailable('Audit storage is not installed.');
            }

            $state = Schema::hasTable(self::STATE_TABLE)
                ? DB::table(self::STATE_TABLE)->where('id', 1)->value('revision')
                : null;
            DB::table(self::AUDIT_TABLE)->insert([
                'correlation_id' => $correlationId,
                'idempotency_key' => null,
                'actor_id' => $actorId,
                'action' => 'posts.reorder',
                'entity_type' => 'published_posts',
                'entity_id' => null,
                'outcome' => $error instanceof OrderConflict ? 'conflict' : 'failure',
                'revision_before' => $state === null ? null : (int) $state,
                'revision_after' => null,
                'payload_hash' => $payloadHash,
                'summary' => json_encode([
                    'reason' => $error instanceof OrderConflict
                        ? $error->reason
                        : ($error instanceof EditorialUnavailable ? 'storage_unavailable' : 'write_failed'),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (Throwable $auditError) {
            Log::warning('Editorial operation could not be audited.', [
                'correlation_id' => $correlationId,
                'error_type' => $auditError::class,
            ]);
        }
    }
}
