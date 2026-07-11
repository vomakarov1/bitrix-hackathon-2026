<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\StageView;

/**
 * Минимальная фейковая реализация StageView для автономного запуска/тестов TUI.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class FakeStage implements StageView
{
    public function __construct(
        private readonly string $label,
        private readonly string $emoji,
        private readonly string $art,
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function emoji(): string
    {
        return $this->emoji;
    }

    public function art(): string
    {
        return $this->art;
    }
}
