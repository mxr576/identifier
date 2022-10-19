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

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Identifier\BinaryIdentifierFactory;
use Identifier\IntegerIdentifierFactory;
use Identifier\StringIdentifierFactory;
use Ramsey\Identifier\Exception\BadMethodCall;
use Ramsey\Identifier\Exception\InvalidArgument;
use Ramsey\Identifier\Exception\RandomSourceNotFound;
use Ramsey\Identifier\Service\RandomGenerator\PhpRandomGenerator;
use Ramsey\Identifier\Service\RandomGenerator\RandomGenerator;
use Ramsey\Identifier\Uuid\Utility\Binary;
use Ramsey\Identifier\Uuid\Utility\StandardUuidFactory;
use Throwable;

use function sprintf;
use function str_pad;
use function substr;

use const STR_PAD_LEFT;

/**
 * A factory for creating Microsoft GUIDs
 *
 * These GUIDs may either be "reserved Microsoft" variant UUIDs or RFC 4122
 * UUIDs using the Microsoft GUID binary encoding. See {@see MicrosoftGuid}
 * for more information on this encoding.
 */
final class MicrosoftGuidFactory implements
    BinaryIdentifierFactory,
    IntegerIdentifierFactory,
    StringIdentifierFactory
{
    use StandardUuidFactory;

    /**
     * Constructs a factory for creating Microsoft GUIDs
     *
     * @param RandomGenerator $randomGenerator A random generator used to
     *     generate bytes; defaults to {@see PhpRandomGenerator}
     */
    public function __construct(
        private readonly RandomGenerator $randomGenerator = new PhpRandomGenerator(),
    ) {
    }

    /**
     * @throws RandomSourceNotFound
     */
    public function create(): MicrosoftGuid
    {
        $bytes = $this->randomGenerator->bytes(16);
        $bytes = Binary::applyVersionAndVariant($bytes, Version::Random, Variant::ReservedMicrosoft);

        return new MicrosoftGuid($this->swapBytes($bytes));
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromBytes(string $identifier): MicrosoftGuid
    {
        /** @var MicrosoftGuid */
        return $this->createFromBytesInternal($identifier);
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromInteger(int | string $identifier): MicrosoftGuid
    {
        try {
            $bigInteger = BigInteger::of($identifier);
        } catch (MathException $exception) {
            throw new InvalidArgument(sprintf('Invalid integer: "%s"', $identifier), 0, $exception);
        }

        try {
            $bytes = str_pad($bigInteger->toBytes(false), 16, "\x00", STR_PAD_LEFT);

            /** @var MicrosoftGuid */
            return $this->createFromBytesInternal($this->swapBytes($bytes));
        } catch (Throwable $exception) {
            throw new InvalidArgument(
                sprintf('Invalid Microsoft GUID: %s', $identifier),
                0,
                $exception,
            );
        }
    }

    /**
     * Returns a "reserved Microsoft" variant Microsoft GUID created from the
     * given RFC 4122 UUID
     *
     * The new GUID returned will be of the "reserved Microsoft" variant. This
     * means some bits will change, and the two values will not be equal.
     *
     * @param UuidV1 | UuidV2 | UuidV3 | UuidV4 | UuidV5 | UuidV6 | UuidV7 | UuidV8 $uuid The UUID to convert to a Microsoft GUID
     */
    // @phpcs:ignore
    public function createFromRfc4122(UuidV1 | UuidV2 | UuidV3 | UuidV4 | UuidV5 | UuidV6 | UuidV7 | UuidV8 $uuid): MicrosoftGuid
    {
        $bytes = Binary::applyVersionAndVariant($uuid->toBytes(), $uuid->getVersion(), Variant::ReservedMicrosoft);

        return new MicrosoftGuid($this->swapBytes($bytes));
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromString(string $identifier): MicrosoftGuid
    {
        /** @var MicrosoftGuid */
        return $this->createFromStringInternal($identifier);
    }

    /**
     * @psalm-mutation-free
     */
    protected function getVersion(): never
    {
        throw new BadMethodCall('getVersion() called out of context'); // @codeCoverageIgnore
    }

    protected function getUuidClass(): string
    {
        return MicrosoftGuid::class;
    }

    private function swapBytes(string $bytes): string
    {
        return $bytes[3] . $bytes[2] . $bytes[1] . $bytes[0]
            . $bytes[5] . $bytes[4]
            . $bytes[7] . $bytes[6]
            . substr($bytes, 8);
    }
}
