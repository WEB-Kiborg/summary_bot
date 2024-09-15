<?php

namespace App\Enums;

enum SummaryFrequencyEnum: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function forHuman(): string
    {
        return match ($this) {
            self::Daily => 'ежедневно',
            self::Weekly => 'еженедельно',
            self::Monthly => 'ежемесячно',
        };
    }
}
