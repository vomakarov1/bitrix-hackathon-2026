<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Character;

/**
 * Реальная фабрика персонажей (§2.3 SDD-B, path 1): обходит статик-связку
 * `Character::create`, инъектируя `Clock`, чтобы момент создания приходил
 * снаружи. Возвращает КОНКРЕТНЫЙ `Character`, чтобы потребитель мог сузить тип.
 */
final class CharacterFactory
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function create(string $name, string $skill, ?string $type): Character
    {
        return Character::create($name, $skill, $type, $this->clock->now());
    }

    public function fromArray(array $data): Character
    {
        return Character::fromArray($data);
    }
}
