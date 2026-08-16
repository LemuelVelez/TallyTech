<?php

use App\Application\Services\AuthService;
use App\Domain\Repositories\ScoringRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testAuthenticateTrimsUsernameAndRemovesPasswordHash(): void
    {
        $hash = password_hash('Admin@12345', PASSWORD_DEFAULT);
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findUserByUsername')
            ->with('admin')
            ->willReturn([
                'id' => 1,
                'username' => 'admin',
                'password_hash' => $hash,
                'display_name' => 'Administrator',
                'role' => 'admin',
                'status' => 'active',
            ]);

        $user = (new AuthService($repository))->authenticate('  admin  ', 'Admin@12345');

        $this->assertNotNull($user);
        $this->assertArrayNotHasKey('password_hash', $user);
        $this->assertSame(1, $user['id']);
    }

    public function testAuthenticateRejectsIncorrectPasswordWithoutLeakingHash(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('findUserByUsername')->willReturn([
            'id' => 1,
            'username' => 'admin',
            'password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
            'display_name' => 'Administrator',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->assertNull((new AuthService($repository))->authenticate('admin', 'wrong-password'));
    }

    public function testAuthenticateRejectsUnknownOrInactiveRepositoryUser(): void
    {
        $repository = $this->createMock(ScoringRepositoryInterface::class);
        $repository->method('findUserByUsername')->willReturn(null);

        $this->assertNull((new AuthService($repository))->authenticate('disabled-user', 'Anything@123'));
    }
}
