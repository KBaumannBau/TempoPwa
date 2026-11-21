<?php

declare(strict_types=1);

namespace App\Tests\Access\Api;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use App\Access\Application\IssueLetterHandler;
use App\Access\Application\UnitOfWork;
use App\Access\Domain\Account;
use App\Access\Domain\AccountRepository;
use App\Access\Domain\Language;
use App\Access\Domain\Username;
use Doctrine\DBAL\Connection;

/**
 * The whole chain: letter → one-time password → own password → device cookie → me → sign-out.
 * Needs the migrated test database: bin/console doctrine:migrations:migrate --env=test
 */
final class AuthenticationFlowTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;
    private const PERSON = '00000000-0000-4000-8000-000000001001';

    private Client $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        /** @var Connection $db */
        $db = $container->get(Connection::class);
        $db->executeStatement('TRUNCATE tempo.device, tempo.invitation, tempo.account, tempo.person_role, tempo.person CASCADE');
        $db->insert('tempo.person', ['id' => self::PERSON, 'personnel_number' => '1001', 'first_name' => 'Stefan', 'last_name' => 'Maurer', 'employment_type' => 'wage_earner']);
        $db->insert('tempo.person_role', ['person_id' => self::PERSON, 'role' => 'skilled_worker']);
        $container->get(AccountRepository::class)->add(Account::invite(self::PERSON, Username::fromString('1001'), Language::German, new \DateTimeImmutable()));
        $container->get(UnitOfWork::class)->commit();
    }

    public function testRegistrationWithLetterThenSignOut(): void
    {
        $letter = static::getContainer()->get(IssueLetterHandler::class)->handle('1001');

        $this->post('/api/auth/login', ['username' => '1001', 'password' => strtolower($letter->oneTimePassword), 'language' => 'pl']);
        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['passwordChangeRequired' => true, 'account' => ['firstName' => 'Stefan', 'role' => 'skilled_worker']]);

        $this->post('/api/auth/password', ['newPassword' => 'kurz']);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['code' => 'password_policy_violated', 'detail' => 'Mindestens 8 Zeichen.']);

        $this->post('/api/auth/password', ['newPassword' => 'Baustelle-2026']);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me', ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['username' => '1001', 'language' => 'pl']);

        $this->post('/api/auth/logout', null);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me', ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseStatusCodeSame(401);
        self::assertJsonContains(['code' => 'unauthenticated']);
    }

    public function testOwnPasswordAndLockAfterFiveWrongAttempts(): void
    {
        $letter = static::getContainer()->get(IssueLetterHandler::class)->handle('1001');
        $this->post('/api/auth/login', ['username' => '1001', 'password' => $letter->oneTimePassword]);
        $this->post('/api/auth/password', ['newPassword' => 'Baustelle-2026']);

        for ($remaining = 4; $remaining >= 1; --$remaining) {
            $this->post('/api/auth/login', ['username' => '1001', 'password' => 'falsch-123']);
            self::assertResponseStatusCodeSame(401);
            self::assertJsonContains(['code' => 'invalid_credentials', 'remainingAttempts' => $remaining]);
        }
        $this->post('/api/auth/login', ['username' => '1001', 'password' => 'falsch-123']);
        self::assertResponseStatusCodeSame(429);
        self::assertJsonContains(['code' => 'temporarily_locked']);

        $this->post('/api/auth/login', ['username' => '1001', 'password' => 'Baustelle-2026']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testSignInOnNewPhoneSignsOutTheOldOne(): void
    {
        $letter = static::getContainer()->get(IssueLetterHandler::class)->handle('1001');
        $this->post('/api/auth/login', ['username' => '1001', 'password' => $letter->oneTimePassword]);
        $this->post('/api/auth/password', ['newPassword' => 'Baustelle-2026']);
        $oldCookie = $this->client->getCookieJar()->get('tempo_device', '/api');
        self::assertNotNull($oldCookie);

        $this->client->getCookieJar()->clear();
        $this->post('/api/auth/login', ['username' => '1001', 'password' => 'Baustelle-2026']);
        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['passwordChangeRequired' => false]);

        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($oldCookie);
        $this->client->request('GET', '/api/auth/me', ['headers' => ['Accept' => 'application/json']]);
        self::assertResponseStatusCodeSame(401);
        self::assertJsonContains(['code' => 'device_signed_out']);
    }

    /** @param array<string, string>|null $json */
    private function post(string $url, ?array $json): void
    {
        $this->client->request('POST', $url, ['json' => $json ?? new \stdClass(), 'headers' => ['Accept' => 'application/json']]);
    }
}
