<?php

namespace App\Support;

use App\Services\Payments\Drivers\IotecDriver;

/**
 * The phone number rule for mobile money (MTN and Airtel in Uganda). While ioTec's sandbox is on (a local machine
 * only) ioTec's own test numbers, such as 0111777771, are accepted too, so the whole flow can be tried without money.
 */
final class MobileMoneyPhone
{
    /** The pattern without delimiters, so the app can use the same one. */
    public static function pattern(): string
    {
        return IotecDriver::sandbox() ? '^((\+?256|0)?7\d{8}|0111\d{6})$' : '^(\+?256|0)?7\d{8}$';
    }

    public static function rule(): string
    {
        return 'regex:/'.self::pattern().'/';
    }
}
