<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Repositories\GenericCrudRepository;

/**
 * In-memory GenericCrudRepository for CAS tests. Skips BaseRepository PDO ctor.
 */
final class FakeCrudRepository extends GenericCrudRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $rowsById = [];
    /** @var list<array{payload: array, expected: array}> */
    public array $updateCalls = [];

    public function __construct()
    {
        // Intentionally skip parent::__construct() — no PDO in unit tests.
    }

    public function findById(string $table, string $primaryKey, int $id): ?array
    {
        return $this->rowsById[$id] ?? null;
    }

    public function updateRecord(string $table, string $primaryKey, int $id, array $payload, array $expected = []): int
    {
        $this->updateCalls[] = ['payload' => $payload, 'expected' => $expected];
        $row = $this->rowsById[$id] ?? null;
        if ($row === null) {
            return 0;
        }
        foreach ($expected as $col => $val) {
            if (!array_key_exists((string) $col, $row) || $row[(string) $col] != $val) {
                return 0;
            }
        }
        $this->rowsById[$id] = array_merge($row, $payload);
        return 1;
    }
}
