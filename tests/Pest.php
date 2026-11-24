<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Totem de Impressão (WIL-46)
|--------------------------------------------------------------------------
*/

/**
 * Minimal valid PDF with the given number of pages. When compressed, the page
 * tree lives inside a Flate-compressed object stream (PDF 1.5+ style).
 */
function totemPdf(int $pages, bool $compressed = false): string
{
    $kids = implode(' ', array_map(fn (int $page): string => ($page + 2).' 0 R', range(1, $pages)));
    $pageTree = "<< /Type /Pages /Kids [{$kids}] /Count {$pages} >>";
    $pageObjects = '';

    foreach (range(1, $pages) as $page) {
        $pageObjects .= ($page + 2)." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n";
    }

    if (! $compressed) {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n{$pageTree}\nendobj\n{$pageObjects}trailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    $objectStream = gzcompress("2 0 {$pageTree}");

    return "%PDF-1.5\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n9 0 obj\n<< /Type /ObjStm /N 1 /Filter /FlateDecode /Length ".strlen($objectStream)." >>\nstream\n{$objectStream}\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * @return array{0: string, 1: array<string, string>}
 */
function totemSignedPixWebhook(string $externalId, string $secret = 'test-webhook-secret'): array
{
    $body = json_encode(['external_id' => $externalId, 'status' => 'paid']);

    return [$body, [
        'Content-Type' => 'application/json',
        'X-Signature' => hash_hmac('sha256', $body, $secret),
    ]];
}
