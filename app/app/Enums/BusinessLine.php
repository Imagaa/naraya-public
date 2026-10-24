<?php

namespace App\Enums;

/**
 * The company's business lines. Products, warehouse stock, stock transfers,
 * sales notes, deposits and the cash book are kept per line; sales people
 * and stores are shared.
 */
enum BusinessLine: string
{
    case Naraya = 'naraya';
    case Toys = 'toys';

    /** The brand-kit line whose logo stands for this business line (public/brand). */
    public function brand(): string
    {
        return match ($this) {
            self::Naraya => 'grosir',
            self::Toys => 'toys',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Naraya => 'Naraya',
            self::Toys => 'Naraya Toys',
        };
    }

    /** One letter for document numbers (NJ-N00012, NJ-T00003). */
    public function initial(): string
    {
        return match ($this) {
            self::Naraya => 'N',
            self::Toys => 'T',
        };
    }
}
