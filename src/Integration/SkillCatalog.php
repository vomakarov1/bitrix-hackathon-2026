<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\PathsInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\SkillCatalogInterface;

/**
 * Перечень доступных локальных скиллов (bare-имена). Владеет К5 (§4.4 SDD-B).
 */
final class SkillCatalog implements SkillCatalogInterface
{
    public function __construct(private readonly PathsInterface $paths)
    {
    }

    /** @return string[] отсортированные bare-имена без дублей. */
    public function all(): array
    {
        $names = [];
        foreach ($this->skillRoots() as $root) {
            foreach ($this->subdirNames($root) as $name) {
                $names[$name] = true;
            }
        }

        $result = array_keys($names);
        sort($result, SORT_STRING);

        return $result;
    }

    /** Глобальный + проектный корень скиллов (§4.4, §9.4 SDD-B). */
    private function skillRoots(): array
    {
        $projectDir = getenv('CLAUDE_PROJECT_DIR');
        $projectRoot = ($projectDir !== false && $projectDir !== '')
            ? rtrim($projectDir, '/')
            : rtrim((string) getcwd(), '/');

        return [
            rtrim($this->paths->claudeConfigDir(), '/').'/skills',
            $projectRoot.'/.claude/skills',
        ];
    }

    /** Отсутствие каталога — не ошибка, вернуть пустой список (§4.4 SDD-B). */
    private function subdirNames(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $names = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($root.'/'.$entry)) {
                $names[] = $entry;
            }
        }

        return $names;
    }
}
