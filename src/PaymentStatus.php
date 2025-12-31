<?php

declare(strict_types=1);

namespace CryptomePay;

/**
 * Payment status constants.
 *
 * @package CryptomePay
 */
final class PaymentStatus
{
    public const PENDING = 1;
    public const PAID = 2;
    public const EXPIRED = 3;

    /**
     * Get human-readable status name.
     *
     * @param int $status
     *
     * @return string
     */
    public static function getName(int $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::EXPIRED => 'Expired',
            default => 'Unknown',
        };
    }

    /**
     * Check if payment is pending.
     *
     * @param int $status
     *
     * @return bool
     */
    public static function isPending(int $status): bool
    {
        return $status === self::PENDING;
    }

    /**
     * Check if payment is paid.
     *
     * @param int $status
     *
     * @return bool
     */
    public static function isPaid(int $status): bool
    {
        return $status === self::PAID;
    }

    /**
     * Check if payment is expired.
     *
     * @param int $status
     *
     * @return bool
     */
    public static function isExpired(int $status): bool
    {
        return $status === self::EXPIRED;
    }
}
