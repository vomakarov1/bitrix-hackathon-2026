<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;

/**
 * In-memory фейковая реализация CharacterRepositoryInterface для автономного
 * запуска/тестов TUI. Ничего не пишет на диск.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class InMemoryCharacterRepository implements CharacterRepositoryInterface
{
    /** @var array<string, FakeCharacter> */
    private array $characters = [];

    /** @param FakeCharacter[] $initial */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $character) {
            $this->add($character);
        }
    }

    public function add(FakeCharacter $character): void
    {
        $this->characters[$character->id()] = $character;
    }

    public function remove(string $id): void
    {
        unset($this->characters[$id]);
    }

    /** @return CharacterView[] */
    public function all(): array
    {
        return array_values($this->characters);
    }

    public function findById(string $id): ?CharacterView
    {
        return $this->characters[$id] ?? null;
    }
}
