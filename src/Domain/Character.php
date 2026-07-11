<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Domain;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\CharacterInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;

/**
 * Персонаж-тамагочи (К1).
 *
 * ФАЗА 2: чистая доменная логика. Класс не обращается к системным часам сам —
 * время (и календарная дата) всегда приходит аргументом снаружи.
 *
 * Внутреннее представление дат (DTO-01): createdAt/lastFedAt/lastStarvedAt
 * хранятся как \DateTimeImmutable|null, сериализуются в ISO-8601 в toArray().
 * lastUsedDate — чистая календарная дата (YYYY-MM-DD), хранится как строка,
 * т.к. сравнивается только по календарю (см. ALG-01/ALG-02), а не по времени.
 */
final class Character implements CharacterInterface, CharacterView
{
    private string $id;

    private string $name;

    private string $skill;

    private ?string $type;

    private int $satiety;

    private int $usageCount;

    private int $streak;

    private int $bestStreak;

    private ?string $lastUsedDate;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $lastFedAt;

    private ?\DateTimeImmutable $lastStarvedAt;

    private function __construct()
    {
    }

    /**
     * Фабрика создания нового персонажа. $now → createdAt; часы инъектируются.
     */
    public static function create(string $name, string $skill, ?string $type, \DateTimeImmutable $now): self
    {
        $character = new self();

        $character->id = bin2hex(random_bytes(16));
        $character->name = $name;
        $character->skill = $skill;
        $character->type = $type;
        $character->satiety = Constants::INITIAL_SATIETY;
        $character->usageCount = 0;
        $character->streak = 0;
        $character->bestStreak = 0;
        $character->lastUsedDate = null;
        $character->createdAt = $now;
        $character->lastFedAt = null;
        $character->lastStarvedAt = null;

        return $character;
    }

    /** Кормёжка: клампит сытость, штампует lastFedAt. */
    public function feed(int $amount, \DateTimeImmutable $now): void
    {
        $this->satiety = $this->clampSatiety($this->satiety + $amount);
        $this->lastFedAt = $now;
    }

    /** Голодание: клампит сытость, штампует lastStarvedAt. */
    public function starve(int $amount, \DateTimeImmutable $now): void
    {
        $this->satiety = $this->clampSatiety($this->satiety - $amount);
        $this->lastStarvedAt = $now;
    }

    /** Учёт использования: usageCount++. Намеренно не штампует время. */
    public function recordUsage(): void
    {
        $this->usageCount++;
    }

    /**
     * Регистрация активного дня: ленивый пересчёт стрика (ALG-01).
     * Единственный мутатор streak/bestStreak/lastUsedDate.
     */
    public function registerActiveDay(string $today): void
    {
        if ($this->lastUsedDate === $today) {
            // Несколько сессий за день не мультиплицируют стрик.
            return;
        }

        if ($this->lastUsedDate === $this->calendarYesterday($today)) {
            $this->streak++;
        } else {
            // null (первый раз) или разрыв > 1 дня.
            $this->streak = 1;
        }

        $this->lastUsedDate = $today;
        $this->bestStreak = max($this->bestStreak, $this->streak);
    }

    /** Признак: персонаж может проголодаться (окно таймаута истекло). */
    public function isStarvable(\DateTimeImmutable $now): bool
    {
        $lastChange = $this->createdAt;

        foreach ([$this->lastFedAt, $this->lastStarvedAt] as $candidate) {
            if ($candidate !== null && $candidate > $lastChange) {
                $lastChange = $candidate;
            }
        }

        $elapsedHours = ($now->getTimestamp() - $lastChange->getTimestamp()) / 3600;

        return $elapsedHours >= Constants::STARVE_TIMEOUT_HOURS;
    }

    /** Живой стрик на дату $today (ALG-02). Чистое чтение, состояние не меняет. */
    public function liveStreak(string $today): int
    {
        if ($this->lastUsedDate === $today || $this->lastUsedDate === $this->calendarYesterday($today)) {
            return $this->streak;
        }

        return 0;
    }

    /** Стадия эволюции, вычисляется из bestStreak. */
    public function stage(): Stage
    {
        return Stage::fromBestStreak($this->bestStreak);
    }

    /** Настроение, вычисляется из satiety. */
    public function mood(): string
    {
        return match (true) {
            $this->satiety >= Constants::MOOD_SATISFIED_AT => '😋 сыт',
            $this->satiety >= Constants::MOOD_OK_AT => '🙂 норм',
            $this->satiety >= Constants::MOOD_HUNGRY_AT => '😟 голоден',
            default => '😫 очень голоден',
        };
    }

    /** Уровень, вычисляется из usageCount. */
    public function level(): int
    {
        return intdiv($this->usageCount, Constants::LEVEL_STEP);
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

    public function satiety(): int
    {
        return $this->satiety;
    }

    public function usageCount(): int
    {
        return $this->usageCount;
    }

    public function streak(): int
    {
        return $this->streak;
    }

    public function bestStreak(): int
    {
        return $this->bestStreak;
    }

    /** Сериализация состояния персонажа (DTO-01). */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'skill' => $this->skill,
            'type' => $this->type,
            'satiety' => $this->satiety,
            'usageCount' => $this->usageCount,
            'streak' => $this->streak,
            'bestStreak' => $this->bestStreak,
            'lastUsedDate' => $this->lastUsedDate,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'lastFedAt' => $this->lastFedAt?->format(\DateTimeInterface::ATOM),
            'lastStarvedAt' => $this->lastStarvedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** Десериализация состояния персонажа (DTO-01); дефолты для старых записей. */
    public static function fromArray(array $data): self
    {
        $character = new self();

        $character->id = $data['id'];
        $character->name = $data['name'];
        $character->skill = $data['skill'];
        $character->type = $data['type'] ?? null;
        $character->satiety = $data['satiety'] ?? Constants::INITIAL_SATIETY;
        $character->usageCount = $data['usageCount'] ?? 0;
        $character->streak = $data['streak'] ?? 0;
        $character->bestStreak = $data['bestStreak'] ?? 0;
        $character->lastUsedDate = $data['lastUsedDate'] ?? null;
        $character->createdAt = new \DateTimeImmutable($data['createdAt']);
        $character->lastFedAt = isset($data['lastFedAt']) ? new \DateTimeImmutable($data['lastFedAt']) : null;
        $character->lastStarvedAt = isset($data['lastStarvedAt']) ? new \DateTimeImmutable($data['lastStarvedAt']) : null;

        return $character;
    }

    /** Клампит значение сытости в [0, MAX_SATIETY]. */
    private function clampSatiety(int $value): int
    {
        return max(0, min(Constants::MAX_SATIETY, $value));
    }

    /** Календарное "вчера" относительно $today (YYYY-MM-DD), тоже YYYY-MM-DD. */
    private function calendarYesterday(string $today): string
    {
        return (new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    }
}
