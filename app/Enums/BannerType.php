<?php

namespace App\Enums;

/**
 * Which storefront slot a banner fills. The type drives placement on the
 * customer homepage — the frontend requests one type per slot (hero carousel,
 * promo trio, eco band, seasonal ribbon) rather than hard-coding content.
 */
enum BannerType: string
{
    case Hero = 'hero';
    case Promo = 'promo';
    case Eco = 'eco';
    case Seasonal = 'seasonal';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
