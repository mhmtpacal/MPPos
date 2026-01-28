<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Banks\KuveytTurk\KuveytTurkAdapter;
use MPPos\Core\AbstractPos;

final class MPPos
{
    public static function kuveytturk(): \MPPos\Banks\KuveytTurk\KuveytTurkAdapter
    {
        return new \MPPos\Banks\KuveytTurk\KuveytTurkAdapter();
    }
}