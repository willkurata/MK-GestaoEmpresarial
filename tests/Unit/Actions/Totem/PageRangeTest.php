<?php

use App\Actions\Totem\PageRange;

it('selects every page when no range is given', function (?string $range) {
    expect(PageRange::pages($range, 3))->toBe([1, 2, 3]);
})->with([null, '', '  ']);

it('expands ranges and single pages without duplicates', function () {
    expect(PageRange::pages('3-5, 1, 4', 6))->toBe([1, 3, 4, 5]);
});

it('rejects ranges outside the document or malformed', function (string $range, string $message) {
    expect(fn () => PageRange::pages($range, 4))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'past the last page' => ['2-5', 'O arquivo tem 4 página(s).'],
    'page zero' => ['0', 'O arquivo tem 4 página(s).'],
    'reversed' => ['3-1', 'O arquivo tem 4 página(s).'],
    'not a range' => ['a-b', 'Use páginas como "1-3,5".'],
]);
