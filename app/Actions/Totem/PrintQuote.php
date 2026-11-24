<?php

namespace App\Actions\Totem;

use App\Models\PrintOrder;
use InvalidArgumentException;

/**
 * Server-side price calculation (the source of truth; the kiosk front only previews it).
 *
 * pages  = pages selected by page_range
 * sides  = ceil(pages / pages_per_sheet)          (printed faces per copy)
 * sheets = duplex ? ceil(sides / 2) : sides       (paper per copy)
 * total  = sides × copies × price per side (by color mode)
 */
class PrintQuote
{
    /**
     * @return array{quote_pages: int, quote_sides: int, quote_sheets: int, quote_per_side: string, quote_total: string}|array<string, null>
     *
     * @throws InvalidArgumentException when the page range does not fit the file
     */
    public static function for(PrintOrder $order): array
    {
        if ($order->page_count === null) {
            return self::empty();
        }

        $pages = count(PageRange::pages($order->page_range, $order->page_count));
        $sides = (int) ceil($pages / $order->pages_per_sheet);
        $sheets = $order->duplex ? (int) ceil($sides / 2) : $sides;
        $pricePerSideInCents = self::toCents((string) $order->totem->pricePerSide($order->color_mode));

        return [
            'quote_pages' => $pages,
            'quote_sides' => $sides,
            'quote_sheets' => $sheets,
            'quote_per_side' => self::fromCents($pricePerSideInCents),
            'quote_total' => self::fromCents($sides * $order->copies * $pricePerSideInCents),
        ];
    }

    /**
     * @return array<string, null>
     */
    public static function empty(): array
    {
        return [
            'quote_pages' => null,
            'quote_sides' => null,
            'quote_sheets' => null,
            'quote_per_side' => null,
            'quote_total' => null,
        ];
    }

    public static function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
