<?php

namespace App\Actions\Totem;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeSvg
{
    public function render(string $contents, int $size = 256): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($contents);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
