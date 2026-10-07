<?php

namespace Tests\Feature;

use App\Services\EditorialOrdering;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\LegacyDatabaseTestCase;

class PostOrderValidationTest extends LegacyDatabaseTestCase
{
    private function orderPayload(array $ids, int $revision = 1, ?string $key = null): array
    {
        return [
            'ids' => $ids,
            'expected_revision' => $revision,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }

    public function test_invalid_order_requests_leave_existing_order_untouched(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Segundo', 'STATUS_POST' => 'PP']);
        $this->post('/admin/posts/order', $this->orderPayload([3, 1]))->assertRedirect();
        $before = DB::table('pena_post_order')->orderBy('post_id')->get()->toJson();
        foreach ([[], [1, 1], [-1, 3], ['invalid', 3], [1.5, 3], ['first' => 1, 'last' => 3]] as $ids) {
            $this->postJson('/admin/posts/order', $this->orderPayload($ids, 2))->assertUnprocessable();
            $this->assertSame($before, DB::table('pena_post_order')->orderBy('post_id')->get()->toJson());
        }
        foreach ([[1], [1, 2, 3], [1, 999]] as $ids) {
            $this->postJson('/admin/posts/order', $this->orderPayload($ids, 2))->assertStatus(409);
            $this->assertSame($before, DB::table('pena_post_order')->orderBy('post_id')->get()->toJson());
        }
    }

    public function test_missing_order_storage_returns_service_unavailable(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/posts/order', $this->orderPayload([1]))
            ->assertStatus(503)->assertJsonPath('message', 'A ordenação está temporariamente indisponível. Nenhuma alteração foi confirmada.');
    }

    public function test_a_newly_published_legacy_post_invalidates_an_old_order_submission(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        $this->post('/admin/posts/order', $this->orderPayload([1]))->assertRedirect();
        $savedOrder = DB::table('pena_post_order')->orderBy('post_id')->get()->toJson();

        // Simulate a legacy writer that does not update the PENA revision row.
        DB::table('POST_pena')->where('ID_POST', 2)->update(['STATUS_POST' => 'PP']);
        $this->postJson('/admin/posts/order', $this->orderPayload([1], 2))->assertStatus(409);
        $this->assertSame($savedOrder, DB::table('pena_post_order')->orderBy('post_id')->get()->toJson());
        $this->assertSame([2, 1], array_column($this->getJson('/api/public/posts')->json('data'), 'id'));
        $audit = DB::table('pena_editorial_audit')->where('outcome', 'conflict')->first();
        $this->assertSame('published_set_changed', json_decode($audit->summary, true)['reason']);
    }

    public function test_html_conflict_restores_the_draft_order_with_titles_and_current_posts(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->insert([
            ['ID_POST' => 3, 'TITULO_POST' => 'Novo artigo publicado', 'STATUS_POST' => 'PP'],
            ['ID_POST' => 4, 'TITULO_POST' => 'Outro artigo publicado', 'STATUS_POST' => 'PP'],
        ]);

        $response = $this->post('/admin/posts/order', $this->orderPayload([4, 1, 2]));

        $response->assertStatus(409)
            ->assertSee('Seu rascunho foi reaplicado à lista atual.')
            ->assertSee('Outro artigo publicado')
            ->assertSee('Publicado')
            ->assertSee('Novo artigo publicado')
            ->assertSee('2');
        preg_match_all('/name="ids\[\]" value="(\d+)"/', $response->getContent(), $matches);
        $this->assertSame(['4', '1', '3'], $matches[1]);
    }

    public function test_two_saves_from_the_same_revision_conflict_without_losing_the_first(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        $this->post('/admin/posts/order', $this->orderPayload([1]))->assertRedirect();
        $this->postJson('/admin/posts/order', $this->orderPayload([1], 1))->assertStatus(409);
        $this->assertDatabaseHas('pena_editorial_state', ['id' => 1, 'revision' => 2]);
        $this->assertSame(1, DB::table('pena_editorial_audit')->where('outcome', 'success')->count());
        $this->assertSame(1, DB::table('pena_editorial_audit')->where('outcome', 'conflict')->count());
        $audit = DB::table('pena_editorial_audit')->where('outcome', 'conflict')->first();
        $this->assertSame('revision_conflict', json_decode($audit->summary, true)['reason']);
    }

    public function test_order_form_keeps_the_revision_that_matches_its_list_during_a_concurrent_save(): void
    {
        $this->installOrderTable();
        $admin = $this->admin();
        $this->actingAs($admin);
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Outro publicado', 'STATUS_POST' => 'PP']);
        $ordering = app(EditorialOrdering::class);
        $ordering->save([1, 3], 1, $admin->id, (string) Str::uuid(), (string) Str::uuid());

        $concurrentSaveCompleted = false;
        DB::listen(function (QueryExecuted $query) use (&$concurrentSaveCompleted, $admin, $ordering): void {
            if ($concurrentSaveCompleted || ! str_contains(strtolower($query->sql), 'sqlite_master') ||
                ! str_contains(strtolower($query->sql), "name = 'pena_editorial_audit'")) {
                return;
            }

            $concurrentSaveCompleted = true;
            $ordering->save([3, 1], 2, $admin->id, (string) Str::uuid(), (string) Str::uuid());
        });

        $page = $this->get('/admin/posts/order')->assertOk();
        $this->assertTrue($concurrentSaveCompleted, 'The test must interleave a save after the list and its revision are captured.');
        $page->assertSee('name="expected_revision" value="2"', false);
        preg_match_all('/name="ids\[\]" value="(\d+)"/', $page->getContent(), $matches);
        $this->assertSame(['1', '3'], $matches[1]);

        $this->post('/admin/posts/order', $this->orderPayload([1, 3], 2))->assertStatus(409);
        $this->assertSame([3, 1], DB::table('pena_post_order')->orderBy('sort_order')->pluck('post_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('pena_editorial_state', ['id' => 1, 'revision' => 3]);
    }

    public function test_reused_idempotency_key_with_another_payload_is_audited_accurately(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        $key = (string) Str::uuid();
        $this->post('/admin/posts/order', $this->orderPayload([1], 1, $key))->assertRedirect();
        $this->postJson('/admin/posts/order', $this->orderPayload([1, 2], 1, $key))->assertStatus(409);

        $audit = DB::table('pena_editorial_audit')->where('outcome', 'conflict')->first();
        $this->assertSame('idempotency_key_reused', json_decode($audit->summary, true)['reason']);
    }

    public function test_retry_with_same_idempotency_key_does_not_duplicate_order_or_success_audit(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        $payload = $this->orderPayload([1]);
        $this->post('/admin/posts/order', $payload)->assertRedirect();
        $this->post('/admin/posts/order', $payload)->assertRedirect();
        $this->assertDatabaseCount('pena_post_order', 1);
        $this->assertSame(1, DB::table('pena_editorial_audit')->where('outcome', 'success')->count());
        $this->assertDatabaseHas('pena_editorial_state', ['id' => 1, 'revision' => 2]);
    }

    public function test_failures_at_every_transaction_checkpoint_roll_back_all_auxiliary_writes(): void
    {
        $this->installOrderTable();
        $actor = $this->admin();
        $ordering = app(EditorialOrdering::class);
        $checkpoints = [
            'after_published_set_check', 'after_order_delete', 'after_order_insert',
            'after_state_update', 'before_success_audit', 'after_success_audit',
        ];

        foreach ($checkpoints as $checkpoint) {
            try {
                $ordering->save([1], 1, $actor->id, (string) Str::uuid(), (string) Str::uuid(),
                    function (string $current) use ($checkpoint): void {
                        if ($current === $checkpoint) {
                            throw new RuntimeException('synthetic failure');
                        }
                    });
                $this->fail('The injected write failure should escape the transaction.');
            } catch (RuntimeException $error) {
                $this->assertSame('synthetic failure', $error->getMessage());
            }

            $this->assertDatabaseHas('pena_editorial_state', ['id' => 1, 'revision' => 1]);
            $this->assertDatabaseCount('pena_post_order', 0);
        }

        $this->assertSame(0, DB::table('pena_editorial_audit')->where('outcome', 'success')->count());
        $this->assertSame(count($checkpoints), DB::table('pena_editorial_audit')->where('outcome', 'failure')->count());
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => 1, 'STATUS_POST' => 'PP']);
    }

    public function test_reordering_never_mutates_legacy_post_rows_and_can_be_reversed(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->where('ID_POST', 2)->update(['STATUS_POST' => 'PP']);
        $before = DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson();

        foreach ([[2, 1], [1, 2]] as $index => $ids) {
            $this->post('/admin/posts/order', $this->orderPayload($ids, $index + 1))->assertRedirect();
            $this->assertSame($ids, array_column($this->getJson('/api/public/posts')->json('data'), 'id'));
            $this->assertSame($before, DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson());
        }
    }
}
