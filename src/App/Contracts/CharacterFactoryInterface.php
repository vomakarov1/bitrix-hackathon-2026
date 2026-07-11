<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Обход статик-связки `Character::create` (§2.3 SDD-B): `CharacterService` зовёт
 * инъектированную фабрику, а не статик-метод — иначе заглушку не подменить.
 * Владелец на интеграции — блок A (или его композиция).
 */
interface CharacterFactoryInterface
{
    public function create(string $name, string $skill, ?string $type): CharacterInterface;

    public function fromArray(array $data): CharacterInterface;
}
