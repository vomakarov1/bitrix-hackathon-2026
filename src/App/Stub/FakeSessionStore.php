<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\SessionStoreInterface;

/**
 * Минимальная in-memory заглушка SessionStoreInterface (§2.3 SDD-B) — только
 * чтобы B собрался и smoke-кейсы прошли до готовности A. В прод не
 * поставляется. Хранит `{usedSkills, updatedAt}` на сессию, `pruneExpired`
 * рабочий.
 */
final class FakeSessionStore implements SessionStoreInterface
{
    /** @var array<string,array{usedSkills:string[],updatedAt:\DateTimeImmutable}> */
    private array $sessions = [];

    public function markSkillUsed(string $sessionId, string $skill, \DateTimeImmutable $now): void
    {
        $session = $this->sessions[$sessionId] ?? ['usedSkills' => [], 'updatedAt' => $now];

        if (!in_array($skill, $session['usedSkills'], true)) {
            $session['usedSkills'][] = $skill;
        }
        $session['updatedAt'] = $now;

        $this->sessions[$sessionId] = $session;
    }

    public function getUsedSkills(string $sessionId): array
    {
        return $this->sessions[$sessionId]['usedSkills'] ?? [];
    }

    public function clear(string $sessionId): void
    {
        unset($this->sessions[$sessionId]);
    }

    public function pruneExpired(int $ttlHours, \DateTimeImmutable $now): void
    {
        foreach ($this->sessions as $sessionId => $session) {
            $ageHours = ($now->getTimestamp() - $session['updatedAt']->getTimestamp()) / 3600;
            if ($ageHours >= $ttlHours) {
                unset($this->sessions[$sessionId]);
            }
        }
    }
}
