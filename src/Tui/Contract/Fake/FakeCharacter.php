<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\StageView;

/**
 * Минимальная фейковая реализация CharacterView для автономного запуска/тестов TUI.
 *
 * Логику подсчёта живого стрика не реализует (это домен блока A) — метод
 * liveStreak() просто возвращает значение, переданное в конструктор.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class FakeCharacter implements CharacterView
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $skill,
        private readonly ?string $type,
        private readonly int $satiety,
        private readonly int $usageCount,
        private readonly string $mood,
        private readonly int $level,
        private readonly int $streak,
        private readonly int $bestStreak,
        private readonly int $liveStreakValue,
        private readonly StageView $stage,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function skill(): string
    {
        return $this->skill;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function satiety(): int
    {
        return $this->satiety;
    }

    public function usageCount(): int
    {
        return $this->usageCount;
    }

    public function mood(): string
    {
        return $this->mood;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function streak(): int
    {
        return $this->streak;
    }

    public function bestStreak(): int
    {
        return $this->bestStreak;
    }

    public function liveStreak(string $today): int
    {
        return $this->liveStreakValue;
    }

    public function stage(): StageView
    {
        return $this->stage;
    }
}
