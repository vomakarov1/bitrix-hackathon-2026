<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\PathsInterface;

/**
 * Мини-логгер ошибок хуков (Р4 ADR, §4.9 SDD-B). Не контракт, деталь
 * реализации B. Дозаписывает в `{dataDir}/hook-errors.log`.
 *
 * Таймстамп берётся из системного времени процесса намеренно (единственное
 * санкционированное исключение из инварианта «время только через Clock», §4.9) —
 * лог диагностический и обязан работать, даже если исключение бросил сам Clock.
 */
final class ErrorLog
{
    public function __construct(private readonly PathsInterface $paths)
    {
    }

    public function write(string $where, \Throwable $e): void
    {
        try {
            $line = sprintf(
                "[%s] %s: %s\n%s\n",
                date('c'),
                $where,
                $e->getMessage(),
                $e->getTraceAsString(),
            );

            $dir = $this->paths->dataDir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            @file_put_contents($dir.'/hook-errors.log', $line, FILE_APPEND);
        } catch (\Throwable) {
            // Сбой логирования не должен мешать инварианту "exit 0" (§4.9 SDD-B).
        }
    }
}
