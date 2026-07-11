<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract;

/**
 * Contract consumed by the TUI presentation layer to describe a growth stage.
 *
 * Corresponds to domain concept K1 (Character/Stage), owned by block A.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
interface StageView
{
    /** Человекочитаемая метка стадии, напр. «Детёныш». */
    public function label(): string;

    /** Эмодзи стадии, напр. 🥚/🐣/🦊/🐉. */
    public function emoji(): string;

    /** ASCII-арт стадии, напр. `(o_o)`. */
    public function art(): string;
}
