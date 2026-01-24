<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Core\PaymentBuilder;

final class MPPos
{
    public const PARAMPOS = 'parampos';
    public const VAKIF    = 'vakif';

    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';


    public const THREED_3D    = '3D';
    public const NONSECURE = 'NS';

    public static function payment(): PaymentBuilder
    {
        return new PaymentBuilder();
    }
}
