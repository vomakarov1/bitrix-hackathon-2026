<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Exception\SkillAlreadyBoundException;
use Vladislavmakarov\BitrixHackathon2026\Domain\Character;
use Vladislavmakarov\BitrixHackathon2026\Integration\SkillCatalog;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterServiceInterface;

/**
 * Создание (валидация скилла+уникальности) и удаление питомца. Владеет К6
 * (§4.2 SDD-B).
 */
final class CharacterService implements CharacterServiceInterface
{
    public function __construct(
        private readonly CharacterRepositoryInterface $repo,
        private readonly SkillCatalog $catalog,
        private readonly CharacterFactory $factory,
    ) {
    }

    /**
     * Валидация «скилл ∈ SkillCatalog» намеренно мягкая (§9.2 OQ-N2): каталог —
     * подсказка UI, а не жёсткое ограничение, питомца можно завести наперёд.
     */
    public function create(string $name, string $skill, ?string $type): Character
    {
        $skill = trim($skill);
        $name = trim($name);

        if ($skill === '') {
            throw new \InvalidArgumentException('Скилл не может быть пустым.');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Имя питомца не может быть пустым.');
        }

        if ($this->repo->findBySkill($skill) !== null) {
            throw SkillAlreadyBoundException::forSkill($skill);
        }

        $pet = $this->factory->create($name, $skill, $type);
        $this->repo->save($pet);

        return $pet;
    }

    public function delete(string $id): void
    {
        $this->repo->delete($id);
    }
}
