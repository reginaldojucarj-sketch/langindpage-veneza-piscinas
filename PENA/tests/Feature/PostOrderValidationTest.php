<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyDatabaseTestCase;

class PostOrderValidationTest extends LegacyDatabaseTestCase
{
    public function test_invalid_order_requests_leave_existing_order_untouched(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Segundo', 'STATUS_POST' => 'PP']);
        $this->post('/admin/posts/order', ['ids' => [3, 1]])->assertRedirect();
        $before = DB::table('pena_post_order')->orderBy('post_id')->get()->toJson();
        foreach ([[], [1], [1, 1], [1, 2, 3], [1, 999], [-1, 3], ['invalid', 3], [1.5, 3], ['first' => 1, 'last' => 3]] as $ids) {
            $this->postJson('/admin/posts/order', ['ids' => $ids])->assertUnprocessable();
            $this->assertSame($before, DB::table('pena_post_order')->orderBy('post_id')->get()->toJson());
        }
    }

    public function test_missing_order_table_has_a_controlled_validation_error(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts/order', ['ids' => [1]])
            ->assertSessionHasErrors('ids');
    }

    public function test_a_newly_published_post_invalidates_an_old_order_submission(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->where('ID_POST', 2)->update(['STATUS_POST' => 'PP']);
        $this->post('/admin/posts/order', ['ids' => [1]])->assertSessionHasErrors('ids');
        $this->assertDatabaseCount('pena_post_order', 0);
    }

    public function test_reordering_does_not_modify_legacy_posts_and_can_be_reversed(): void
    {
        $this->installOrderTable();
        $this->actingAs($this->admin());
        DB::table('POST_pena')->where('ID_POST', 2)->update(['STATUS_POST' => 'PP']);
        $before = DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson();
        foreach ([[2, 1], [1, 2]] as $ids) {
            $this->post('/admin/posts/order', ['ids' => $ids])->assertRedirect();
            $this->assertSame($ids, array_column($this->getJson('/api/public/posts')->json('data'), 'id'));
            $this->assertSame($before, DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson());
        }
    }
}
