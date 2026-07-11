<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Contracts;

/**
 * Контракт К1 (владелец — блок A) — ровно те методы, что B читает/мутирует
 * (§2.3, §9.1 C1–C2 SDD-B). Заведён блоком B для автономной сборки/тестов;
 * на интеграции — либо A реализует этот интерфейс, либо тайп-хинты B меняются
 * на конкретный класс A.
 */
interface CharacterInterface
{
    public function id(): string;

    public function name(): string;

    public function skill(): string;

    public function type(): ?string;

    public function streak(): int;

    public function bestStreak(): int;

    public function feed(int $amount, \DateTimeImmutable $now): void;

    public function starve(int $amount, \DateTimeImmutable $now): void;

    public function recordUsage(): void;

    public function satiety(): int;

    public function usageCount(): int;

    public function mood(): string;

    public function level(): int;

    public function isStarvable(\DateTimeImmutable $now): bool;

    public function registerActiveDay(string $today): void;

    public function liveStreak(string $today): int;

    public function stage(): StageInterface;

    public function toArray(): array;
}
