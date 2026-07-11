<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake;

use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterServiceInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\SkillCatalogInterface;

/**
 * In-memory фейковая реализация CharacterServiceInterface для автономного
 * запуска/тестов TUI. Ничего не пишет на диск, не считает реальный домен
 * (стрики/уровни/сытость) — только создаёт персонажа с MVP-дефолтами.
 *
 * @todo TEMPORARY: удалить/заменить после реализации реальных контрактов блоков A/B (см. SDD §1.1).
 */
final class InMemoryCharacterService implements CharacterServiceInterface
{
    private int $counter = 0;

    public function __construct(
        private readonly InMemoryCharacterRepository $repository,
        private readonly ?SkillCatalogInterface $skillCatalog = null,
    ) {
    }

    public function create(string $name, string $skill, ?string $type): CharacterView
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Имя питомца не должно быть пустым.');
        }

        if ('' === trim($skill)) {
            throw new \InvalidArgumentException('Скилл не должен быть пустым.');
        }

        if (null !== $this->skillCatalog && !\in_array($skill, $this->skillCatalog->all(), true)) {
            throw new \InvalidArgumentException(sprintf('Неизвестный скилл "%s".', $skill));
        }

        foreach ($this->repository->all() as $existing) {
            if ($existing->skill() === $skill) {
                throw new \RuntimeException(sprintf('Скилл "%s" уже занят.', $skill));
            }
        }

        $id = sprintf('char-%d-%s', ++$this->counter, uniqid());

        $character = new FakeCharacter(
            id: $id,
            name: $name,
            skill: $skill,
            type: $type,
            satiety: 50,
            usageCount: 0,
            mood: 'нейтрально',
            level: 1,
            streak: 0,
            bestStreak: 0,
            liveStreakValue: 0,
            stage: new FakeStage('Яйцо', '🥚', '.oOo.'),
        );

        $this->repository->add($character);

        return $character;
    }

    public function delete(string $id): void
    {
        $this->repository->remove($id);
    }
}
