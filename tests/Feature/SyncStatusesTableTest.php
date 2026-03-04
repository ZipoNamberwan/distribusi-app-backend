<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncStatusesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_statuses_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sync_statuses'));

        $this->assertTrue(Schema::hasColumns('sync_statuses', [
            'id',
            'status',
            'message',
            'created_at',
            'updated_at',
        ]));
    }
}
