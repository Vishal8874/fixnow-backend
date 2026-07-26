<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case ONLINE = 'online';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
}
