<?php

namespace App\Application\Services;

use App\Domain\Repositories\ScoringRepositoryInterface;

class AuthService
{
    public function __construct(private ScoringRepositoryInterface $repository) {}

    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->repository->findUserByUsername(trim($username));
        if (! $user || ! password_verify($password, $user['password_hash'])) return null;
        unset($user['password_hash']);
        return $user;
    }
}
