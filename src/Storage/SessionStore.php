<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\SessionStoreInterface;

/**
 * Хранилище состояния сессий (К3).
 *
 * ФАЗА 3: локальное JSON-хранилище `sessions.json` (карта
 * session_id → { usedSkills: string[], updatedAt: ISO-8601 }, DTO-02).
 *
 * Дисциплина RMW — та же, что в CharacterRepository: эксклюзивный flock()
 * на отдельном lock-файле `sessions.json.lock`, чтение, применение колбэка,
 * атомарная запись через temp-файл в dataDir + rename(), снятие лока.
 */
final class SessionStore implements SessionStoreInterface
{
    private const string FILE_NAME = 'sessions.json';

    private const string LOCK_FILE_NAME = 'sessions.json.lock';

    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function markSkillUsed(string $sessionId, string $skill, \DateTimeImmutable $now): void
    {
        $this->mutate(static function (array $sessions) use ($sessionId, $skill, $now): array {
            /** @var string[] $usedSkills */
            $usedSkills = $sessions[$sessionId]['usedSkills'] ?? [];

            if (!in_array($skill, $usedSkills, true)) {
                $usedSkills[] = $skill;
            }

            $sessions[$sessionId] = [
                'usedSkills' => array_values($usedSkills),
                'updatedAt' => $now->format(\DateTimeInterface::ATOM),
            ];

            return $sessions;
        });
    }

    /** @return string[] */
    public function getUsedSkills(string $sessionId): array
    {
        $sessions = $this->readSessionsRaw();

        return $sessions[$sessionId]['usedSkills'] ?? [];
    }

    public function clear(string $sessionId): void
    {
        $this->mutate(static function (array $sessions) use ($sessionId): array {
            unset($sessions[$sessionId]);

            return $sessions;
        });
    }

    public function pruneExpired(int $ttlHours, \DateTimeImmutable $now): void
    {
        $this->mutate(static function (array $sessions) use ($ttlHours, $now): array {
            foreach ($sessions as $sessionId => $record) {
                $updatedAtRaw = $record['updatedAt'] ?? null;

                if ($updatedAtRaw === null) {
                    // Битая запись без updatedAt — считаем протухшей, убираем.
                    unset($sessions[$sessionId]);
                    continue;
                }

                $updatedAt = new \DateTimeImmutable($updatedAtRaw);
                $elapsedHours = ($now->getTimestamp() - $updatedAt->getTimestamp()) / 3600;

                if ($elapsedHours > $ttlHours) {
                    unset($sessions[$sessionId]);
                }
            }

            return $sessions;
        });
    }

    /**
     * Read-modify-write под одним flock (общий примитив с CharacterRepository,
     * продублирован намеренно — минимальный код, общий базовый класс не нужен).
     *
     * @param callable(array<string, array{usedSkills: string[], updatedAt: string}>): array<string, array{usedSkills: string[], updatedAt: string}> $fn
     */
    private function mutate(callable $fn): void
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

            $sessions = $this->readSessionsRaw();
            $sessions = $fn($sessions);
            $this->writeSessionsRaw($sessions);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** @return array<string, array{usedSkills: string[], updatedAt: string}> */
    private function readSessionsRaw(): array
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
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return [];
        }

        return $decoded;
    }

    /** @param array<string, array{usedSkills: string[], updatedAt: string}> $sessions */
    private function writeSessionsRaw(array $sessions): void
    {
        $json = $sessions === []
            ? '{}'
            : json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode sessions.json.');
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
