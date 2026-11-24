<?php

namespace App\Http\Controllers\Totem;

use App\Http\Controllers\Controller;
use App\Http\Resources\Totem\TotemDisplayResource;
use App\Models\Totem;

class TotemDisplayController extends Controller
{
    /**
     * Kiosk screen state: printer, public queue, prices and the rotating QR code.
     */
    public function __invoke(Totem $totem): TotemDisplayResource
    {
        abort_unless($totem->isActive(), 404);

        return new TotemDisplayResource($totem);
    }
}
