<?php

namespace App\Exceptions;

use App\PrintOrderStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidPrintOrderTransition extends RuntimeException
{
    public function __construct(public PrintOrderStatus $from, public PrintOrderStatus $to)
    {
        parent::__construct("Pedido em \"{$from->value}\" não pode ir para \"{$to->value}\".");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'status' => $this->from->value,
        ], 409);
    }
}
