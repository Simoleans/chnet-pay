<?php

namespace App\Enums;

enum PlanPriceAdjustment: string
{
    case TvCoaxial = 'tv_coaxial';
    case Tv = 'tv';
    case Internet = 'internet';
    case SuperNetSinTv = 'super_net_sin_tv';

    public static function fromPlanName(string $planName): self
    {
        $normalizedName = strtoupper($planName);

        if (str_contains($normalizedName, 'SUPERNET SIN TV')) {
            return self::SuperNetSinTv;
        }

        if (str_contains($normalizedName, 'TV COAXIAL')) {
            return self::TvCoaxial;
        }

        if (str_contains($normalizedName, 'TV')) {
            return self::Tv;
        }



        return self::Internet;
    }

    public function amount(): float
    {
        return match ($this) {
            self::TvCoaxial => 0.0,
            self::Tv => 3.0,
            self::Internet => 4.5,
            self::SuperNetSinTv => 0.0,
        };
    }
}
