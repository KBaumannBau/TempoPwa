<?php

declare(strict_types=1);

namespace App\Access\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Credential of a registered phone: device id plus 32 random bytes. Only the SHA-256 of the secret is stored;
 * a slow hash is not needed for a random secret of this length. Never log the token.
 */
final readonly class DeviceKey
{
    private const SECRET_BYTES = 32;

    private function __construct(public string $deviceId, private string $secret)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122(), random_bytes(self::SECRET_BYTES));
    }

    public static function fromToken(string $token): ?self
    {
        $parts = explode('.', $token, 2);
        if (\count($parts) !== 2 || !Uuid::isValid($parts[0])) {
            return null;
        }
        $secret = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($secret === false || \strlen($secret) !== self::SECRET_BYTES) {
            return null;
        }

        return new self(strtolower($parts[0]), $secret);
    }

    public function token(): string
    {
        return $this->deviceId.'.'.rtrim(strtr(base64_encode($this->secret), '+/', '-_'), '=');
    }

    public function hash(): string
    {
        return hash('sha256', $this->secret);
    }
}
