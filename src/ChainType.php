<?php

declare(strict_types=1);

namespace CryptomePay;

/**
 * Supported blockchain networks.
 *
 * @package CryptomePay
 */
final class ChainType
{
    public const TRC20 = 'TRC20';
    public const BSC = 'BSC';
    public const POLYGON = 'POLYGON';
    public const ETH = 'ETH';
    public const ARBITRUM = 'ARBITRUM';

    /**
     * Get all supported chain types.
     *
     * @return array
     */
    public static function all(): array
    {
        return [
            self::TRC20,
            self::BSC,
            self::POLYGON,
            self::ETH,
            self::ARBITRUM,
        ];
    }

    /**
     * Check if a chain type is valid.
     *
     * @param string $chainType
     *
     * @return bool
     */
    public static function isValid(string $chainType): bool
    {
        return in_array($chainType, self::all(), true);
    }
}
