<?php

namespace App\Services;

use App\Contracts\PaymentDriver;
use App\Models\PlatformSetting;
use App\Services\Payments\Drivers\AirtelMoneyDriver;
use App\Services\Payments\Drivers\IotecDriver;
use App\Services\Payments\Drivers\ManualDriver;
use App\Services\Payments\Drivers\MtnMomoDriver;
use App\Services\Payments\Drivers\SandboxDriver;
use App\Services\Payments\Drivers\StripeDriver;
use InvalidArgumentException;

/**
 * Driver-based payment manager.
 *
 *   PaymentService::driver('iotec')->charge($donation);
 *   PaymentService::driver('mtn_momo')->charge($donation);
 *   PaymentService::driver('airtel_money')->charge($donation);
 *   PaymentService::driver('stripe')->charge($donation);
 *
 * When PAYMENTS_SANDBOX=true, or a gateway has no credentials configured,
 * the SandboxDriver simulates it so the whole flow works locally.
 */
class PaymentService
{
    /** @var array<string, class-string<PaymentDriver>> */
    protected const DRIVERS = [
        'mtn_momo' => MtnMomoDriver::class,
        'airtel_money' => AirtelMoneyDriver::class,
        'iotec' => IotecDriver::class,
        'stripe' => StripeDriver::class,
        'bank' => ManualDriver::class,
        'cash' => ManualDriver::class,
        'ussd' => IotecDriver::class,
    ];

    /** @var array<string, PaymentDriver> */
    protected static array $resolved = [];

    public static function driver(string $method): PaymentDriver
    {
        if (! array_key_exists($method, self::DRIVERS)) {
            throw new InvalidArgumentException("Unsupported payment method [$method].");
        }

        return static::$resolved[$method] ??= static::make($method);
    }

    protected static function make(string $method): PaymentDriver
    {
        $class = self::DRIVERS[$method];

        if ($class === ManualDriver::class) {
            return new ManualDriver($method);
        }

        if (config('faithfellows.payments.sandbox')) {
            return new SandboxDriver($method);
        }

        // MTN and Airtel mobile money are collected through ioTec when their own gateways are not set up.
        if (in_array($method, ['mtn_momo', 'airtel_money'], true) && ! $class::configured() && IotecDriver::configured()) {
            return app(IotecDriver::class);
        }

        if (! $class::configured()) {
            return new SandboxDriver($method);
        }

        return app($class);
    }

    /**
     * Platform kill switch per gateway (Super admin → Payment gateways), e.g.
     * during a provider outage. Cash/bank recording is never switched off.
     */
    public static function enabled(string $method): bool
    {
        if (in_array($method, ['cash', 'bank'], true)) {
            return true;
        }

        return (string) rescue(fn () => PlatformSetting::get("gateway.{$method}.enabled", '1'), '1', false) !== '0';
    }

    /**
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return array_keys(self::DRIVERS);
    }

    public static function isSandbox(string $method): bool
    {
        return static::driver($method) instanceof SandboxDriver;
    }

    /** Reset cached drivers (tests / config changes). */
    public static function flush(): void
    {
        static::$resolved = [];
    }
}
