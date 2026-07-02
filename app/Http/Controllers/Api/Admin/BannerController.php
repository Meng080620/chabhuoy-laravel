<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Every banner, active or not, ordered the way the storefront renders them.
     * Not paginated: the CMS content set is small and editors want it all.
     */
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(
            Banner::query()->ordered()->get()
        );
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create($data);

        return BannerResource::make($banner)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            // Replace: drop the previous file so storage doesn't accumulate orphans.
            $this->deleteImage($banner);
            $data['image_path'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        return BannerResource::make($banner);
    }

    public function destroy(Banner $banner): Response
    {
        $this->deleteImage($banner);
        $banner->delete();

        return response()->noContent();
    }

    private function deleteImage(Banner $banner): void
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
    }
}
