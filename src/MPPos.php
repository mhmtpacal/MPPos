<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Banks\KuveytTurk\KuveytTurkAdapter;

final class MPPos
{
    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    public static function kuveytturk(): KuveytTurkAdapter
    {
        return new KuveytTurkAdapter();
    }
}
