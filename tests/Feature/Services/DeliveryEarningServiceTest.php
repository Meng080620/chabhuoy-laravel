<?php

namespace Tests\Feature\Services;

use App\Exceptions\DisbursementFailedException;
use App\Models\DeliveryMan;
use App\Services\Contracts\DisbursementProvider;
use App\Services\DeliveryEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryEarningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_disbursement_stores_the_provider_reference(): void
    {
        $rider = DeliveryMan::factory()->create(['wallet_balance' => '90.00']);

        $earning = $this->app->make(DeliveryEarningService::class)->process($rider);

        $this->assertNotNull($earning);
        $this->assertSame('90.00', (string) $earning->amount);
        $this->assertStringStartsWith('disb_', (string) $earning->reference);
        $this->assertSame('0.00', (string) $rider->refresh()->wallet_balance);
    }

    public function test_a_provider_failure_leaves_the_wallet_intact_and_writes_no_row(): void
    {
        $this->app->instance(DisbursementProvider::class, new class implements DisbursementProvider
        {
            public function send(string $amount, string $reference): string
            {
                throw new DisbursementFailedException('Wallet top-up rejected.');
            }
        });
        $rider = DeliveryMan::factory()->create(['wallet_balance' => '40.00']);

        try {
            $this->app->make(DeliveryEarningService::class)->process($rider);
            $this->fail('Expected DisbursementFailedException.');
        } catch (DisbursementFailedException) {
            // expected
        }

        $this->assertSame('40.00', (string) $rider->refresh()->wallet_balance);
        $this->assertSame(0, $rider->earnings()->count());
    }
}
