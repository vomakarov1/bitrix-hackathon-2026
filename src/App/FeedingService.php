<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\SessionStoreInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Оркестратор ядра механики (§4.1 SDD-B). Единственный держатель `Clock` в
 * рантайме хуков.
 */
final class FeedingService
{
    public function __construct(
        private readonly CharacterRepositoryInterface $repo,
        private readonly SessionStoreInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Путь hook:tool-use. Возвращает обновлённого питомца на успех, иначе null. */
    public function recordSkillUsage(string $sessionId, string $skill): ?CharacterInterface
    {
        $found = null;

        $this->repo->mutate(function (array &$characters) use ($skill, &$found): void {
            foreach ($characters as $character) {
                if ($character->skill() === $skill) {
                    $character->recordUsage();
                    $found = $character;

                    return;
                }
            }
        });

        if ($found === null) {
            return null;
        }

        $this->sessions->markSkillUsed($sessionId, $skill, $this->clock->now());

        return $found;
    }

    /** Путь hook:session-end. Подводит итог: кормёжка+стрик / таймаут-голод; чистит сессию. */
    public function settleSession(string $sessionId): void
    {
        $used = $this->sessions->getUsedSkills($sessionId);
        $now = $this->clock->now();
        $today = $this->clock->today();

        $this->repo->mutate(function (array &$characters) use ($used, $now, $today): void {
            foreach ($characters as $character) {
                if (in_array($character->skill(), $used, true)) {
                    $character->registerActiveDay($today);
                    $bonus = min($character->liveStreak($today), Constants::STREAK_BONUS_CAP) * Constants::STREAK_FEED_STEP;
                    $character->feed(Constants::FEED_PER_SESSION + $bonus, $now);
                } elseif ($character->isStarvable($now)) {
                    $character->starve(Constants::STARVE_STEP, $now);
                }
            }
        });

        $this->sessions->clear($sessionId);
    }
}
