<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterRepositoryInterface;

/**
 * Минимальная in-memory заглушка CharacterRepositoryInterface (§2.3 SDD-B) —
 * только чтобы B собрался и smoke-кейсы прошли до готовности A. В прод не
 * поставляется. Барьер уникальности `skill` работает как у К2 (save кидает
 * при дубле).
 */
final class FakeRepository implements CharacterRepositoryInterface
{
    /** @var array<string,CharacterInterface> */
    private array $characters = [];

    public function findById(string $id): ?CharacterInterface
    {
        return $this->characters[$id] ?? null;
    }

    public function findBySkill(string $skill): ?CharacterInterface
    {
        foreach ($this->characters as $character) {
            if ($character->skill() === $skill) {
                return $character;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->characters);
    }

    public function save(CharacterInterface $character): void
    {
        $existing = $this->findBySkill($character->skill());
        if ($existing !== null && $existing->id() !== $character->id()) {
            throw new \RuntimeException(sprintf('Скилл "%s" уже занят другим питомцем.', $character->skill()));
        }

        $this->characters[$character->id()] = $character;
    }

    public function delete(string $id): void
    {
        unset($this->characters[$id]);
    }

    public function mutate(callable $mutator): void
    {
        // Fake-эквивалент RMW-под-flock (Р1 ADR): $characters передаётся по
        // ссылке, объекты внутри уже мутабельны (feed/starve меняют состояние
        // in-place), так что колбэк может как менять существующих питомцев,
        // так и добавлять/удалять записи в массиве.
        $mutator($this->characters);
    }
}
