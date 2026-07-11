<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\StageInterface;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Минимальная in-memory заглушка CharacterInterface (§2.3 SDD-B) — реализует
 * доменную логику B0 корректно (клампы/пороги/стрик по дням), но упрощённо —
 * только чтобы B собрался и smoke-кейсы прошли до готовности A. В прод не
 * поставляется.
 */
final class FakeCharacter implements CharacterInterface
{
    private int $satiety;
    private int $usageCount = 0;
    private ?\DateTimeImmutable $lastFedAt = null;
    private ?\DateTimeImmutable $lastStarvedAt = null;
    private ?string $lastActiveDay = null;
    private int $streak = 0;
    private int $bestStreak = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $skill,
        private readonly ?string $type,
        private readonly \DateTimeImmutable $createdAt,
        ?int $satiety = null,
    ) {
        $this->satiety = $this->clamp($satiety ?? Constants::INITIAL_SATIETY);
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $character = new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            skill: (string) ($data['skill'] ?? ''),
            type: $data['type'] ?? null,
            createdAt: isset($data['createdAt']) ? new \DateTimeImmutable((string) $data['createdAt']) : new \DateTimeImmutable(),
            satiety: isset($data['satiety']) ? (int) $data['satiety'] : null,
        );

        $character->usageCount = (int) ($data['usageCount'] ?? 0);
        $character->lastFedAt = isset($data['lastFedAt']) ? new \DateTimeImmutable((string) $data['lastFedAt']) : null;
        $character->lastStarvedAt = isset($data['lastStarvedAt']) ? new \DateTimeImmutable((string) $data['lastStarvedAt']) : null;
        $character->lastActiveDay = isset($data['lastActiveDay']) ? (string) $data['lastActiveDay'] : null;
        $character->streak = (int) ($data['streak'] ?? 0);
        $character->bestStreak = (int) ($data['bestStreak'] ?? 0);

        return $character;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function skill(): string
    {
        return $this->skill;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function streak(): int
    {
        return $this->streak;
    }

    public function bestStreak(): int
    {
        return $this->bestStreak;
    }

    public function feed(int $amount, \DateTimeImmutable $now): void
    {
        $this->satiety = $this->clamp($this->satiety + $amount);
        $this->lastFedAt = $now;
    }

    public function starve(int $amount, \DateTimeImmutable $now): void
    {
        $this->satiety = $this->clamp($this->satiety - $amount);
        $this->lastStarvedAt = $now;
    }

    public function recordUsage(): void
    {
        ++$this->usageCount;
    }

    public function satiety(): int
    {
        return $this->satiety;
    }

    public function usageCount(): int
    {
        return $this->usageCount;
    }

    public function mood(): string
    {
        return match (true) {
            $this->satiety >= Constants::MOOD_SATISFIED_AT => 'сыт',
            $this->satiety >= Constants::MOOD_OK_AT => 'норм',
            $this->satiety >= Constants::MOOD_HUNGRY_AT => 'голоден',
            default => 'очень голоден',
        };
    }

    public function level(): int
    {
        return intdiv($this->usageCount, Constants::LEVEL_STEP) + 1;
    }

    public function isStarvable(\DateTimeImmutable $now): bool
    {
        $reference = $this->createdAt;
        foreach ([$this->lastFedAt, $this->lastStarvedAt] as $candidate) {
            if ($candidate !== null && $candidate > $reference) {
                $reference = $candidate;
            }
        }

        $hoursSince = ($now->getTimestamp() - $reference->getTimestamp()) / 3600;

        return $hoursSince >= Constants::STARVE_TIMEOUT_HOURS;
    }

    public function registerActiveDay(string $today): void
    {
        if ($this->lastActiveDay === $today) {
            return;
        }

        $yesterday = (new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
        $this->streak = ($this->lastActiveDay === $yesterday) ? $this->streak + 1 : 1;
        $this->lastActiveDay = $today;
        $this->bestStreak = max($this->bestStreak, $this->streak);
    }

    public function liveStreak(string $today): int
    {
        if ($this->lastActiveDay === null) {
            return 0;
        }

        $yesterday = (new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');

        return ($this->lastActiveDay === $today || $this->lastActiveDay === $yesterday) ? $this->streak : 0;
    }

    public function stage(): StageInterface
    {
        $level = $this->level();

        return match (true) {
            $level >= Constants::STAGE_LEGEND_AT => new FakeStage('🐉', 'легенда'),
            $level >= Constants::STAGE_BEAST_AT => new FakeStage('🦊', 'зверь'),
            $level >= Constants::STAGE_HATCHLING_AT => new FakeStage('🐣', 'детёныш'),
            default => new FakeStage('🥚', 'яйцо'),
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'skill' => $this->skill,
            'type' => $this->type,
            'satiety' => $this->satiety,
            'usageCount' => $this->usageCount,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'lastFedAt' => $this->lastFedAt?->format(DATE_ATOM),
            'lastStarvedAt' => $this->lastStarvedAt?->format(DATE_ATOM),
            'lastActiveDay' => $this->lastActiveDay,
            'streak' => $this->streak,
            'bestStreak' => $this->bestStreak,
        ];
    }

    private function clamp(int $value): int
    {
        return max(0, min(Constants::MAX_SATIETY, $value));
    }
}
