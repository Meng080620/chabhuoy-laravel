<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandStoreRequest;
use App\Http\Requests\Admin\UpdateBrandStoreRequest;
use App\Http\Resources\BrandStoreResource;
use App\Models\BrandStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandStoreController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandStoreResource::collection(
            BrandStore::query()->ordered()->get()
        );
    }

    public function store(StoreBrandStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('brand-stores', 'public');
        }

        $store = BrandStore::create($data);

        return BrandStoreResource::make($store)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateBrandStoreRequest $request, BrandStore $brandStore): BrandStoreResource
    {
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $this->deleteLogo($brandStore);
            $data['logo_path'] = $request->file('logo')->store('brand-stores', 'public');
        }

        $brandStore->update($data);

        return BrandStoreResource::make($brandStore);
    }

    public function destroy(BrandStore $brandStore): Response
    {
        $this->deleteLogo($brandStore);
        $brandStore->delete();

        return response()->noContent();
    }

    private function deleteLogo(BrandStore $brandStore): void
    {
        if ($brandStore->logo_path) {
            Storage::disk('public')->delete($brandStore->logo_path);
        }
    }
}
