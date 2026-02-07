<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Banks\KuveytTurk\KuveytTurkAdapter;
use MPPos\Banks\ParamPos\ParamPosAdapter;

final class MPPos
{
    public static function kuveytturk(): KuveytTurkAdapter
    {
        return new KuveytTurkAdapter();
    }

    public static function parampos(): ParamPosAdapter
    {
        return new ParamPosAdapter();
    }
}