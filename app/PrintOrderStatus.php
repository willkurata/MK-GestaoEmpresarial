<?php

namespace App;

enum PrintOrderStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case Queued = 'queued';
    case Printing = 'printing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /**
     * Allowed state machine transitions; anything else is rejected.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::AwaitingPayment, self::Canceled, self::Expired, self::Failed],
            self::AwaitingPayment => [self::Queued, self::Canceled, self::Expired],
            self::Queued => [self::Printing, self::Failed, self::Refunded],
            self::Printing => [self::Ready, self::Queued, self::Failed],
            self::Failed => [self::Queued, self::Refunded],
            self::Expired, self::Canceled => [self::Refunded],
            self::Ready, self::Refunded => [],
        }, true);
    }
}
