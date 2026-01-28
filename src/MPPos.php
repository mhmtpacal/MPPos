<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Banks\KuveytTurk\KuveytTurkAdapter;

final class MPPos
{
    public static function kuveytturk(): KuveytTurkAdapter
    {
        return new KuveytTurkAdapter();
    }
}