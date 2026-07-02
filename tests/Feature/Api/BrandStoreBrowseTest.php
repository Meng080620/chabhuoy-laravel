<?php

namespace Tests\Feature\Api;

use App\Models\BrandStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandStoreBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_lists_only_active_brand_stores_ordered_by_position(): void
    {
        BrandStore::factory()->create(['name' => 'B', 'position' => 2]);
        BrandStore::factory()->create(['name' => 'A', 'position' => 1]);
        BrandStore::factory()->inactive()->create(['name' => 'Hidden', 'position' => 0]);

        $this->getJson('/api/brand-stores')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonPath('data.1.name', 'B')
            ->assertJsonMissing(['name' => 'Hidden']);
    }
}
