<?php

namespace App\Http\Controllers\CyberCoffe;

use App\Http\Controllers\Controller;
use App\Models\CyberCoffe\Attendant;
use App\Models\CyberCoffe\Store;
use Illuminate\Http\Request;

abstract class CyberCoffeController extends Controller
{
    /**
     * The store bound to the authenticated token. Every query must go through it.
     */
    protected function currentStore(Request $request): Store
    {
        return $request->attributes->get('cybercoffe.store');
    }

    protected function currentAttendant(Request $request): Attendant
    {
        return $request->attributes->get('cybercoffe.user');
    }
}
