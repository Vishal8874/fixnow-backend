<?php

namespace App\Enums;

enum BookingStatus: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PENDING_ASSIGNMENT = 'pending_assignment';
    case PROVIDER_ASSIGNED = 'provider_assigned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
