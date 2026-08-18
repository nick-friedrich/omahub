<?php

namespace App\Enums;

enum SecurityScanStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
