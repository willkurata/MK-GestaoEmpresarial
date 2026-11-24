<?php

namespace App\Actions\Totem\Pix;

final readonly class PixCharge
{
    public function __construct(public string $externalId, public string $copyPaste) {}
}
