<?php

/**
 * This file is part of ramsey/identifier
 *
 * ramsey/identifier is open source software: you can distribute
 * it and/or modify it under the terms of the MIT License
 * (the "License"). You may not use this file except in
 * compliance with the License.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Ramsey\Identifier\Uuid;

use BadMethodCallException;
use Identifier\Uuid\UuidInterface;
use Identifier\Uuid\Variant;
use InvalidArgumentException;
use Ramsey\Identifier\Uuid;

use function sprintf;
use function strlen;
use function strtolower;

/**
 * The Nil UUID is a special form of UUID that is specified to have all 128
 * bits set to zero (0)
 *
 * @link https://datatracker.ietf.org/doc/html/rfc4122#section-4.1.7 RFC 4122, § 4.1.7
 *
 * @psalm-immutable
 */
final class NilUuid implements UuidInterface
{
    use StandardUuid;

    public function __construct(string $uuid = Uuid::NIL)
    {
        if (!$this->isValid($uuid)) {
            throw new InvalidArgumentException(sprintf('Invalid Nil UUID: "%s"', $uuid));
        }

        $this->uuid = $uuid;
    }

    public function getVariant(): Variant
    {
        // Nil UUIDs are defined according to the rules of RFC 4122, so they are
        // an RFC 4122 variant of UUID.
        return Variant::Rfc4122;
    }

    public function getVersion(): never
    {
        throw new BadMethodCallException('Nil UUIDs do not have a version field');
    }

    private function isValid(string $uuid): bool
    {
        return match (strlen($uuid)) {
            36 => strtolower($uuid) === Uuid::NIL,
            32 => strtolower($uuid) === '00000000000000000000000000000000',
            16 => $uuid === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00",
            default => false,
        };
    }
}
