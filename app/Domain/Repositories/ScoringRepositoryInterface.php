<?php

namespace App\Domain\Repositories;

interface ScoringRepositoryInterface
{
    public function findUserByUsername(string $username): ?array;
    public function activeEvent(): ?array;
    public function events(): array;
    public function teams(): array;
    public function sports(?int $eventId = null): array;
    public function locations(): array;
    public function schedules(?int $eventId = null, ?string $resultType = null): array;
    public function usersByRole(string $role): array;
    public function assignedSportIds(int $userId): array;
    public function notifications(int $limit = 30): array;
    public function weightedPoints(?int $eventId = null): array;
    public function results(?int $eventId = null, ?string $type = null): array;
    public function resultEntries(int $resultId): array;
    public function ranking(?int $eventId = null): array;
    public function reportSummary(?int $eventId = null): array;
    public function createTeam(array $data, int $actorId): int;
    public function createEvent(array $data, int $actorId): int;
    public function activateEvent(int $eventId, int $actorId): void;
    public function createSport(array $data, int $actorId): int;
    public function createSchedule(array $data, int $actorId): int;
    public function createUser(array $data, array $sportIds, int $actorId): int;
    public function saveWeightedPoints(array $data, int $actorId): int;
    public function validateWeightedPoints(int $id, int $actorId): void;
    public function createResult(array $data, int $actorId): int;
    public function validateResult(int $id, int $actorId): void;
    public function updateUserSettings(int $userId, array $settings): void;
    public function getUserSettings(int $userId): array;
    public function deleteTeam(int $id, int $actorId): void;
}
