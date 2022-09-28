<?php

/**
 * This file is part of ramsey/identifier
 *
 * ramsey/identifier is open source software: you can distribute
 * it and/or modify it under the terms of the MIT License
 * (the "License"). You may not use this file except in
 * compliance with the License.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Ramsey\Identifier\Uuid;

use Identifier\Uuid\Variant;
use Identifier\Uuid\Version;
use Ramsey\Identifier\Mask;
use Ramsey\Identifier\Uuid\Dce\Domain;

use function count;
use function explode;
use function hexdec;
use function strlen;
use function strspn;
use function substr;
use function unpack;

use const PHP_INT_MIN;

/**
 * This internal trait provides common validation functionality for RFC 4122 UUIDs
 *
 * @internal
 *
 * @psalm-immutable
 */
trait Validation
{
    /**
     * Returns the Version enum for the UUID type represented by the class
     * using this trait
     *
     * We use this in {@see self::isValid()} to determine whether the UUID is
     * valid for the type the class represents.
     */
    abstract protected function getVersion(): Version;

    /**
     * Given an integer value of the variant bits, this returns the variant
     * associated with those bits
     *
     * @link https://datatracker.ietf.org/doc/html/rfc4122#section-4.1.1 Variant
     */
    private function determineVariant(int $value): Variant
    {
        return match (true) {
            $value >> 1 === 7 => Variant::ReservedFuture,
            $value >> 1 === 6 => Variant::ReservedMicrosoft,
            $value >> 2 === 2 => Variant::Rfc4122,
            default => Variant::ReservedNcs,
        };
    }

    /**
     * Returns a Domain for the UUID, if available
     *
     * The domain field is only relevant to version 2 UUIDs.
     */
    private function getLocalDomainFromUuid(string $uuid, ?Format $format): ?Domain
    {
        return match ($format) {
            Format::String => Domain::tryFrom((int) hexdec(substr($uuid, 21, 2))),
            Format::Hexadecimal => Domain::tryFrom((int) hexdec(substr($uuid, 18, 2))),
            Format::Bytes => Domain::tryFrom((static function (string $bytes): int {
                /** @var int[] $parts */
                $parts = unpack('n*', "\x00" . substr($bytes, 9, 1));

                // If $parts[1] is not set, return an integer that won't
                // exist in Domain, so that Domain::tryFrom() returns null.
                return $parts[1] ?? PHP_INT_MIN;
            })($uuid)),
            default => null,
        };
    }

    /**
     * Returns the UUID variant, if available
     */
    private function getVariantFromUuid(string $uuid, ?Format $format): ?Variant
    {
        return match ($format) {
            Format::String => $this->determineVariant((int) hexdec(substr($uuid, 19, 1))),
            Format::Hexadecimal => $this->determineVariant((int) hexdec(substr($uuid, 16, 1))),
            Format::Bytes => $this->determineVariant(
                (
                    function (string $uuid): int {
                        /** @var positive-int[] $parts */
                        $parts = unpack('n*', $uuid, 8);

                        return $parts[1] >> 12;
                    }
                )(
                    $uuid,
                ),
            ),
            default => null,
        };
    }

    /**
     * Returns the UUID version, if available
     */
    private function getVersionFromUuid(string $uuid, ?Format $format): ?int
    {
        return match ($format) {
            Format::String => (int) hexdec(substr($uuid, 14, 1)),
            Format::Hexadecimal => (int) hexdec(substr($uuid, 12, 1)),
            Format::Bytes => (static function (string $uuid): int {
                /** @var positive-int[] $parts */
                $parts = unpack('n*', $uuid, 6);

                return ($parts[1] & 0xf000) >> 12;
            })($uuid),
            default => null,
        };
    }

    /**
     * Returns true if the given string standard, hexadecimal, or bytes
     * representation of a UUID has a valid format
     */
    private function hasValidFormat(string $uuid, ?Format $format): bool
    {
        return match ($format) {
            Format::String => $this->isValidStringLayout($uuid, Mask::HEX),
            Format::Hexadecimal => strspn($uuid, Mask::HEX) === 32,
            Format::Bytes => true,
            default => false,
        };
    }

    /**
     * Returns true if the given string standard, hexadecimal, or bytes
     * representation of a UUID is a Max UUID
     */
    private function isMax(string $uuid, ?Format $format): bool
    {
        return match ($format) {
            Format::String => $this->isValidStringLayout($uuid, Mask::MAX),
            Format::Hexadecimal => strspn($uuid, Mask::MAX) === 32,
            Format::Bytes => $uuid === "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff",
            default => false,
        };
    }

    /**
     * Returns true if the given string standard, hexadecimal, or bytes
     * representation of a UUID is a Nil UUID
     */
    private function isNil(string $uuid, ?Format $format): bool
    {
        return match ($format) {
            Format::String => $uuid === '00000000-0000-0000-0000-000000000000',
            Format::Hexadecimal => $uuid === '00000000000000000000000000000000',
            Format::Bytes => $uuid === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00",
            default => false,
        };
    }

    /**
     * Validates a UUID according to the RFC 4122 layout
     *
     * The UUID may be in string standard, hexadecimal, or bytes representation.
     */
    private function isValid(string $uuid, ?Format $format): bool
    {
        return $this->hasValidFormat($uuid, $format)
            && $this->getVariantFromUuid($uuid, $format) === Variant::Rfc4122
            && $this->getVersionFromUuid($uuid, $format) === $this->getVersion()->value;
    }

    /**
     * Returns true if the UUID is a valid string standard representation
     *
     * @param string $mask Typically a hexadecimal mask but may also be used to
     *     validate alternate masks, such as with Max UUIDs
     */
    private function isValidStringLayout(string $uuid, string $mask): bool
    {
        $format = explode('-', $uuid);

        return count($format) === 5
            && strlen($format[0]) === 8
            && strlen($format[1]) === 4
            && strlen($format[2]) === 4
            && strlen($format[3]) === 4
            // There's no need to count the 5th segment,
            // since we already know the length of the string.
            // && strlen($format[4]) === 12
            && strspn($uuid, "-$mask") === 36;
    }
}
