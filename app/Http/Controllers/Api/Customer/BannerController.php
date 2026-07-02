<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\BannerType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    /**
     * Active storefront banners, ordered for rendering. An optional ?type=
     * filter lets the homepage fetch one slot at a time (hero, promo, …).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(BannerType::class)],
        ]);

        $banners = Banner::query()
            ->active()
            ->when(
                $validated['type'] ?? null,
                fn ($query, string $type) => $query->where('type', $type),
            )
            ->ordered()
            ->get();

        return BannerResource::collection($banners);
    }
}
