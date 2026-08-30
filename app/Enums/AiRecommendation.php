<?php

namespace App\Enums;

enum AiRecommendation: string
{
    case Install = 'install';
    case Review = 'review';
    case Avoid = 'avoid';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
