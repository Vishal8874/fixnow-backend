<?php

namespace App\Enums;

enum BookingStatus: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PENDING_ASSIGNMENT = 'pending_assignment';
    case PROVIDER_ASSIGNED = 'provider_assigned';
    case ON_THE_WAY = 'on_the_way';
    case ARRIVED = 'arrived';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
