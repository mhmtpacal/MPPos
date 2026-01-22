<?php
declare(strict_types=1);

namespace MPPos;

final class MPPos
{
    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    /**
     * Desteklenen bankalar
     */
    public const KUVEYT_TURK = 'kuveyt_turk';

    public static function request(): RequestBuilder
    {
        return new RequestBuilder();
    }
}
