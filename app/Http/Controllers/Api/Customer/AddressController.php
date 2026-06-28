<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // The first address a user adds is always their default; otherwise honour
        // an explicit is_default flag. Create non-default, then promote atomically
        // so the partial-unique index is never momentarily violated.
        $shouldDefault = $user->addresses()->count() === 0 || ($data['is_default'] ?? false);
        $data['is_default'] = false;

        $address = $user->addresses()->create($data);

        if ($shouldDefault) {
            $this->makeDefault($user, $address);
        }

        return AddressResource::make($address->refresh())
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreAddressRequest $request, Address $address): AddressResource
    {
        $this->ensureOwned($request, $address);

        $data = $request->validated();
        $wantsDefault = $data['is_default'] ?? false;
        unset($data['is_default']);

        $address->update($data);

        if ($wantsDefault) {
            $this->makeDefault($request->user(), $address);
        }

        return AddressResource::make($address->refresh());
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->ensureOwned($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        // Don't leave the user defaultless if other addresses remain.
        if ($wasDefault) {
            $next = $request->user()->addresses()->latest('id')->first();

            if ($next !== null) {
                $this->makeDefault($request->user(), $next);
            }
        }

        return response()->json(['message' => 'Address deleted.']);
    }

    public function setDefault(Request $request, Address $address): AddressResource
    {
        $this->ensureOwned($request, $address);
        $this->makeDefault($request->user(), $address);

        return AddressResource::make($address->refresh());
    }

    /**
     * Promote one address to default and demote the rest, in a single
     * transaction so the "one default per user" invariant always holds.
     */
    private function makeDefault(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $user->addresses()
                ->whereKeyNot($address->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);
        });
    }

    /**
     * 404 — not 403 — when the address isn't the caller's, so the existence of
     * other users' addresses isn't leaked.
     */
    private function ensureOwned(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
