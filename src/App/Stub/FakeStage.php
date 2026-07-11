<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\StageInterface;

/**
 * Минимальная in-memory заглушка StageInterface (§2.3 SDD-B) — только чтобы B
 * собрался и smoke-кейсы прошли до готовности A. В прод не поставляется.
 */
final class FakeStage implements StageInterface
{
    public function __construct(private readonly string $emoji, private readonly string $label)
    {
    }

    public function emoji(): string
    {
        return $this->emoji;
    }

    public function label(): string
    {
        return $this->label;
    }
}
