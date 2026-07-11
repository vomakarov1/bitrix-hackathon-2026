<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * API стадии эволюции питомца (§9.1 C4, §4.5 SDD-B). Владелец домена — блок A;
 * B её только читает (эмодзи+лейбл для карточки/списка).
 */
interface StageInterface
{
    public function emoji(): string;

    public function label(): string;
}
