<?php

namespace App\Enums;

enum AiReviewStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
