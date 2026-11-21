<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use App\Access\Application\RegistrationTicket;
use App\Access\Domain\Language;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signs the registration ticket (HMAC-SHA-256 with the app secret) so it can live in a short-lived cookie
 * without server-side state. Valid for 10 minutes.
 */
final readonly class RegistrationTicketCodec
{
    public const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')] #[\SensitiveParameter] private string $secret,
        private ClockInterface $clock,
    ) {
    }

    public function encode(RegistrationTicket $ticket): string
    {
        $payload = self::base64Url((string) json_encode([
            'i' => $ticket->invitationId,
            'l' => $ticket->language->value,
            'd' => $ticket->deviceLabel,
            'e' => $this->clock->now()->getTimestamp() + self::TTL_SECONDS,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->sign($payload);
    }

    public function decode(?string $token): ?RegistrationTicket
    {
        $parts = explode('.', (string) $token, 2);
        if (\count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!\is_array($data) || !\is_string($data['i'] ?? null) || !\is_int($data['e'] ?? null) || $data['e'] < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return new RegistrationTicket($data['i'], Language::fromCodeOrDefault(\is_string($data['l'] ?? null) ? $data['l'] : null), \is_string($data['d'] ?? null) ? $data['d'] : 'Browser');
    }

    private function sign(string $payload): string
    {
        return self::base64Url(hash_hmac('sha256', 'access-registration:'.$payload, $this->secret, true));
    }

    private static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
