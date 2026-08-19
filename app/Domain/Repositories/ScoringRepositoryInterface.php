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
    public function allLocations(): array;
    public function sportCategories(bool $includeInactive = false): array;
    public function sportCategory(int $id): ?array;
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
    public function updateTeam(int $id, array $data, int $actorId): void;
    public function deleteTeam(int $id, int $actorId): void;

    public function createEvent(array $data, int $actorId): int;
    public function updateEvent(int $id, array $data, int $actorId): void;
    public function activateEvent(int $eventId, int $actorId): void;
    public function deleteEvent(int $id, int $actorId): void;

    public function createLocation(string $name, int $actorId): int;
    public function updateLocation(int $id, string $name, int $actorId): void;
    public function setLocationActive(int $id, bool $isActive, int $actorId): void;
    public function deleteLocation(int $id, int $actorId): void;

    public function createSportCategory(string $name, int $actorId): int;
    public function updateSportCategory(int $id, string $name, int $actorId): void;
    public function setSportCategoryActive(int $id, bool $isActive, int $actorId): void;
    public function deleteSportCategory(int $id, int $actorId): void;

    public function createSport(array $data, int $actorId): int;
    public function updateSport(int $id, array $data, int $actorId): void;
    public function deleteSport(int $id, int $actorId): void;

    public function createSchedule(array $data, int $actorId): int;
    public function updateSchedule(int $id, array $data, int $actorId): void;
    public function deleteSchedule(int $id, int $actorId): void;

    public function createUser(array $data, array $sportIds, int $actorId): int;
    public function updateUser(int $id, array $data, array $sportIds, int $actorId): void;
    public function deleteUser(int $id, int $actorId): void;

    public function saveWeightedPoints(array $data, int $actorId): int;
    public function updateWeightedPoints(int $id, array $data, int $actorId): void;
    public function validateWeightedPoints(int $id, int $actorId): void;
    public function deleteWeightedPoints(int $id, int $actorId): void;

    public function createResult(array $data, int $actorId): int;
    public function updateResult(int $id, array $data, int $actorId): void;
    public function validateResult(int $id, int $actorId): void;
    public function deleteResult(int $id, int $actorId): void;

    public function updateUserSettings(int $userId, array $settings): void;
    public function getUserSettings(int $userId): array;
}
