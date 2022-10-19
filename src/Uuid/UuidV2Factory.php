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

use DateTimeInterface;
use Identifier\BinaryIdentifierFactory;
use Identifier\DateTimeIdentifierFactory;
use Identifier\IntegerIdentifierFactory;
use Identifier\StringIdentifierFactory;
use Ramsey\Identifier\Exception\DceIdentifierNotFound;
use Ramsey\Identifier\Exception\InvalidArgument;
use Ramsey\Identifier\Exception\InvalidCacheKey;
use Ramsey\Identifier\Exception\MacAddressNotFound;
use Ramsey\Identifier\Exception\RandomSourceNotFound;
use Ramsey\Identifier\Service\Clock\SystemClock;
use Ramsey\Identifier\Service\Counter\Counter;
use Ramsey\Identifier\Service\Counter\RandomCounter;
use Ramsey\Identifier\Service\Dce\Dce;
use Ramsey\Identifier\Service\Dce\SystemDce;
use Ramsey\Identifier\Service\Nic\FallbackNic;
use Ramsey\Identifier\Service\Nic\Nic;
use Ramsey\Identifier\Service\Nic\RandomNic;
use Ramsey\Identifier\Service\Nic\StaticNic;
use Ramsey\Identifier\Service\Nic\SystemNic;
use Ramsey\Identifier\Uuid\Utility\Binary;
use Ramsey\Identifier\Uuid\Utility\StandardUuidFactory;
use Ramsey\Identifier\Uuid\Utility\Time;
use StellaMaris\Clock\ClockInterface as Clock;

use function hex2bin;
use function pack;
use function sprintf;
use function substr;

/**
 * A factory for creating version 2, DCE Security UUIDs
 */
final class UuidV2Factory implements
    BinaryIdentifierFactory,
    DateTimeIdentifierFactory,
    IntegerIdentifierFactory,
    StringIdentifierFactory
{
    use StandardUuidFactory;

    /**
     * Constructs a factory for creating version 2, DCE Security UUIDs
     *
     * @param Clock $clock A clock used to provide a date-time instance;
     *     defaults to {@see SystemClock}
     * @param Counter $counter A counter that provides the next value in a
     *     sequence to prevent collisions; defaults to {@see RandomCounter}
     * @param Dce $dce A service that provides local identifiers when creating
     *     version 2 UUIDs; defaults to {@see SystemDce}
     * @param Nic $nic A NIC that provides the system MAC address value;
     *     defaults to {@see FallbackNic}, with {@see SystemNic} and
     *     {@see RandomNic} as fallbacks
     */
    public function __construct(
        private readonly Clock $clock = new SystemClock(),
        private readonly Counter $counter = new RandomCounter(),
        private readonly Dce $dce = new SystemDce(),
        private readonly Nic $nic = new FallbackNic([new SystemNic(), new RandomNic()]),
    ) {
    }

    /**
     * @param DceDomain $localDomain The local domain to which the local identifier
     *     belongs; this defaults to "Person," and if $localIdentifier is not
     *     provided, the factory will attempt to obtain a suitable local ID for
     *     the domain (e.g., the UID or GID of the user running the script)
     * @param int<0, max> | null $localIdentifier A local identifier belonging
     *     to the local domain specified in $localDomain; if no identifier is
     *     provided, the factory will attempt to obtain a suitable local ID for
     *     the domain (e.g., the UID or GID of the user running the script)
     * @param int<0, max> | string | null $node A 48-bit integer or hexadecimal
     *     string representing the hardware address of the machine where this
     *     identifier was generated
     * @param int<0, 63> | null $clockSequence A 6-bit number used to help
     *     avoid duplicates that could arise when the clock is set backwards in
     *     time or if the node ID changes
     * @param DateTimeInterface | null $dateTime A date-time to use when
     *     creating the identifier
     *
     * @throws InvalidCacheKey
     * @throws DceIdentifierNotFound
     * @throws InvalidArgument
     * @throws MacAddressNotFound
     * @throws RandomSourceNotFound
     *
     * @psalm-param int<0, max> | non-empty-string | null $node
     */
    public function create(
        DceDomain $localDomain = DceDomain::Person,
        ?int $localIdentifier = null,
        int | string | null $node = null,
        ?int $clockSequence = null,
        ?DateTimeInterface $dateTime = null,
    ): UuidV2 {
        $localIdentifier = $localIdentifier ?? match ($localDomain) {
            DceDomain::Person => $this->dce->userId(),
            DceDomain::Group => $this->dce->groupId(),
            default => $this->dce->orgId(),
        };

        $node = $node === null ? $this->nic->address() : (new StaticNic($node))->address();
        $clockSequence = ($clockSequence ?? $this->counter->next()) % 16384;
        $dateTime = $dateTime ?? $this->clock->now();

        $timeBytes = Time::getTimeBytesForGregorianEpoch($dateTime);

        $bytes = pack('N', $localIdentifier)
            . substr($timeBytes, 2, 2)
            . substr($timeBytes, 0, 2)
            . pack('n', $clockSequence)[1]
            . pack('n', $localDomain->value)[1]
            . hex2bin(sprintf('%012s', $node));

        $bytes = Binary::applyVersionAndVariant($bytes, Version::DceSecurity);

        return new UuidV2($bytes);
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromBytes(string $identifier): UuidV2
    {
        /** @var UuidV2 */
        return $this->createFromBytesInternal($identifier);
    }

    /**
     * @throws InvalidCacheKey
     * @throws DceIdentifierNotFound
     * @throws InvalidArgument
     * @throws MacAddressNotFound
     * @throws RandomSourceNotFound
     */
    public function createFromDateTime(DateTimeInterface $dateTime): UuidV2
    {
        return $this->create(dateTime: $dateTime);
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromInteger(int | string $identifier): UuidV2
    {
        /** @var UuidV2 */
        return $this->createFromIntegerInternal($identifier);
    }

    /**
     * @throws InvalidArgument
     */
    public function createFromString(string $identifier): UuidV2
    {
        /** @var UuidV2 */
        return $this->createFromStringInternal($identifier);
    }

    /**
     * @psalm-mutation-free
     */
    protected function getVersion(): Version
    {
        return Version::DceSecurity;
    }

    protected function getUuidClass(): string
    {
        return UuidV2::class;
    }
}
