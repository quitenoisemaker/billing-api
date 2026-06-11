<?php

namespace App\Enums;

enum PaymentStatus: string
{
    //

    case PENDING = 'pending';
    case SUCCESSFUL = 'successful';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
}
