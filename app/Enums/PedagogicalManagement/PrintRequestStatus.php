<?php

namespace App\Enums\PedagogicalManagement;

enum PrintRequestStatus: string
{
    case Pending = 'pending';
    case InProcess = 'in_process';
    case Printed = 'printed';
    case Completed = 'completed';
}
