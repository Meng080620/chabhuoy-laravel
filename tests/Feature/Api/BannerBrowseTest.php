<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_only_active_banners_ordered_by_position(): void
    {
        Banner::factory()->create(['type' => 'hero', 'title' => 'B', 'position' => 2]);
        Banner::factory()->create(['type' => 'hero', 'title' => 'A', 'position' => 1]);
        Banner::factory()->inactive()->create(['type' => 'hero', 'title' => 'Hidden', 'position' => 0]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'A')
            ->assertJsonPath('data.1.title', 'B')
            ->assertJsonMissing(['title' => 'Hidden']);
    }

    public function test_public_can_filter_banners_by_type(): void
    {
        Banner::factory()->create(['type' => 'hero', 'title' => 'Hero one']);
        Banner::factory()->create(['type' => 'promo', 'title' => 'Promo one']);

        $this->getJson('/api/banners?type=promo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Promo one');
    }

    public function test_an_unknown_type_filter_is_rejected(): void
    {
        $this->getJson('/api/banners?type=bogus')
            ->assertStatus(422);
    }
}
