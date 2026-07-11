<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Exception;

/**
 * Скилл уже привязан к другому питомцу (§4.2 SDD-B, барьер уникальности К6/К2).
 */
final class SkillAlreadyBoundException extends \RuntimeException
{
    public static function forSkill(string $skill): self
    {
        return new self(sprintf('Скилл "%s" уже занят другим питомцем.', $skill));
    }
}
