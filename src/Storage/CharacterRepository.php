<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

use Vladislavmakarov\BitrixHackathon2026\Domain\Character;

/**
 * Хранилище персонажей (К2).
 *
 * ФАЗА 3: локальное JSON-хранилище `characters.json` (массив записей DTO-01).
 *
 * Дисциплина RMW (см. mutate()): эксклюзивный flock() на ОТДЕЛЬНОМ lock-файле
 * `characters.json.lock`, чтение текущего состояния, применение колбэка,
 * атомарная запись через temp-файл в том же каталоге + rename(), снятие лока.
 * Lock — на отдельном файле (а не на дескрипторе самого characters.json),
 * т.к. на Windows rename() поверх открытого/залоченного файла падает;
 * отдельный lock-файл снимает эту проблему на POSIX и Windows одинаково.
 */
final class CharacterRepository
{
    private const string FILE_NAME = 'characters.json';

    private const string LOCK_FILE_NAME = 'characters.json.lock';

    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function findById(string $id): ?Character
    {
        foreach ($this->all() as $character) {
            if ($character->id() === $id) {
                return $character;
            }
        }

        return null;
    }

    public function findBySkill(string $skill): ?Character
    {
        foreach ($this->all() as $character) {
            if ($character->skill() === $skill) {
                return $character;
            }
        }

        return null;
    }

    /** @return Character[] */
    public function all(): array
    {
        return array_map(
            static fn (array $record): Character => Character::fromArray($record),
            $this->readRecordsRaw()
        );
    }

    /** Сохраняет персонажа (insert/update по id); throws при дубле skill у другого id. */
    public function save(Character $character): void
    {
        $this->mutate(static function (array $records) use ($character): array {
            $newRecord = $character->toArray();
            $updated = [];
            $found = false;

            foreach ($records as $record) {
                if ($record['id'] === $newRecord['id']) {
                    $updated[] = $newRecord;
                    $found = true;
                    continue;
                }

                if ($record['skill'] === $newRecord['skill']) {
                    throw new \RuntimeException(
                        sprintf('Character with skill "%s" already exists.', $newRecord['skill'])
                    );
                }

                $updated[] = $record;
            }

            if (!$found) {
                $updated[] = $newRecord;
            }

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $this->mutate(static function (array $records) use ($id): array {
            return array_values(array_filter(
                $records,
                static fn (array $record): bool => $record['id'] !== $id
            ));
        });
    }

    /**
     * Read-modify-write под одним flock.
     * $fn получает текущий массив "сырых" записей (как из json_decode) и
     * должен вернуть новый массив записей того же вида — он записывается
     * атомарно на диск. save()/delete() реализованы через этот примитив.
     *
     * @param callable(array<int, array<string, mixed>>): array<int, array<string, mixed>> $fn
     */
    public function mutate(callable $fn): void
    {
        $this->ensureDataDir();

        $lockPath = $this->lockFilePath();
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            throw new \RuntimeException("Cannot open lock file: {$lockPath}");
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException("Cannot acquire exclusive lock: {$lockPath}");
            }

            $records = $this->readRecordsRaw();
            $records = $fn($records);
            $this->writeRecordsRaw($records);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function readRecordsRaw(): array
    {
        $path = $this->filePath();

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return array_values($decoded);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function writeRecordsRaw(array $records): void
    {
        $json = json_encode(
            array_values($records),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode characters.json.');
        }

        $tmpPath = $this->dataDir . DIRECTORY_SEPARATOR . self::FILE_NAME . '.' . uniqid('tmp', true) . '.tmp';

        if (file_put_contents($tmpPath, $json) === false) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to write temp file: {$tmpPath}");
        }

        if (!rename($tmpPath, $this->filePath())) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to rename temp file to: {$this->filePath()}");
        }
    }

    private function ensureDataDir(): void
    {
        if (is_dir($this->dataDir)) {
            return;
        }

        if (!mkdir($this->dataDir, 0777, true) && !is_dir($this->dataDir)) {
            throw new \RuntimeException("Cannot create data directory: {$this->dataDir}");
        }
    }

    private function filePath(): string
    {
        return $this->dataDir . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    private function lockFilePath(): string
    {
        return $this->dataDir . DIRECTORY_SEPARATOR . self::LOCK_FILE_NAME;
    }
}
