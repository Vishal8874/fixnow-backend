<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case COMPLETED = 'completed';
}
