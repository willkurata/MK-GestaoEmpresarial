<?php

namespace App\Actions\Totem;

use InvalidArgumentException;

class PageRange
{
    /**
     * Expand a range such as "1-3,5" into the page numbers to print.
     * A null or empty range means every page.
     *
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function pages(?string $range, int $pageCount): array
    {
        if ($range === null || trim($range) === '') {
            return range(1, $pageCount);
        }

        $pages = [];

        foreach (explode(',', $range) as $part) {
            if (! preg_match('/^\s*(\d+)\s*(?:-\s*(\d+)\s*)?$/', $part, $matches)) {
                throw new InvalidArgumentException('Use páginas como "1-3,5".');
            }

            $first = (int) $matches[1];
            $last = isset($matches[2]) ? (int) $matches[2] : $first;

            if ($first < 1 || $last < $first || $last > $pageCount) {
                throw new InvalidArgumentException("O arquivo tem {$pageCount} página(s).");
            }

            array_push($pages, ...range($first, $last));
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        return $pages;
    }
}
