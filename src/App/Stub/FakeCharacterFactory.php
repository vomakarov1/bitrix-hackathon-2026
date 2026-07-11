<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterFactoryInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface;

/**
 * Минимальная in-memory заглушка CharacterFactoryInterface (§2.3 SDD-B) —
 * только чтобы B собрался и smoke-кейсы прошли до готовности A. В прод не
 * поставляется.
 */
final class FakeCharacterFactory implements CharacterFactoryInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function create(string $name, string $skill, ?string $type): CharacterInterface
    {
        return new FakeCharacter(
            id: bin2hex(random_bytes(8)),
            name: $name,
            skill: $skill,
            type: $type,
            createdAt: $this->clock->now(),
        );
    }

    public function fromArray(array $data): CharacterInterface
    {
        return FakeCharacter::fromArray($data);
    }
}
