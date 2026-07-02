<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandStoreResource;
use App\Models\BrandStore;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandStoreController extends Controller
{
    /** Active brand-store tiles for the homepage, ordered for rendering. */
    public function index(): AnonymousResourceCollection
    {
        return BrandStoreResource::collection(
            BrandStore::query()->active()->ordered()->get()
        );
    }
}
