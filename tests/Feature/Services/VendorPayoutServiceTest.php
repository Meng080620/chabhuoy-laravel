<?php

namespace Tests\Feature\Services;

use App\Exceptions\DisbursementFailedException;
use App\Models\Vendor;
use App\Services\Contracts\DisbursementProvider;
use App\Services\VendorPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_payout_stores_the_provider_reference(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => '120.00']);

        $payout = $this->app->make(VendorPayoutService::class)->process($vendor);

        $this->assertNotNull($payout);
        $this->assertSame('120.00', (string) $payout->amount);
        $this->assertStringStartsWith('disb_', (string) $payout->reference);
        $this->assertSame('0.00', (string) $vendor->refresh()->payout_balance);
    }

    public function test_a_provider_failure_leaves_the_balance_intact_and_writes_no_row(): void
    {
        // A payout rail that always rejects — the transaction must roll back whole.
        $this->app->instance(DisbursementProvider::class, new class implements DisbursementProvider
        {
            public function send(string $amount, string $reference): string
            {
                throw new DisbursementFailedException('Bank rejected the transfer.');
            }
        });
        $vendor = Vendor::factory()->create(['payout_balance' => '75.00']);

        try {
            $this->app->make(VendorPayoutService::class)->process($vendor);
            $this->fail('Expected DisbursementFailedException.');
        } catch (DisbursementFailedException) {
            // expected
        }

        // Balance preserved for retry; no ledger row written.
        $this->assertSame('75.00', (string) $vendor->refresh()->payout_balance);
        $this->assertSame(0, $vendor->payouts()->count());
    }
}
