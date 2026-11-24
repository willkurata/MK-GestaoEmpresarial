<?php

use App\Actions\Totem\PdfPageCounter;

it('counts the pages of an uncompressed pdf', function () {
    expect((new PdfPageCounter)->count(totemPdf(3)))->toBe(3);
});

it('counts the pages when the page tree is inside a compressed object stream', function () {
    expect((new PdfPageCounter)->count(totemPdf(7, compressed: true)))->toBe(7);
});

it('returns null for content that is not a pdf', function () {
    expect((new PdfPageCounter)->count('not a pdf /Type /Pages /Count 4'))->toBeNull();
});

it('returns null for an encrypted pdf', function () {
    $encryptedPdf = str_replace('<< /Root 1 0 R >>', '<< /Root 1 0 R /Encrypt 5 0 R >>', totemPdf(2));

    expect((new PdfPageCounter)->count($encryptedPdf))->toBeNull();
});
