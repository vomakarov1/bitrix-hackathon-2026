<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

/**
 * Защитный парсер payload хука Claude Code (§4.3 SDD-B). Никогда не бросает на
 * кривом вводе — возвращает объект с `null`-полями.
 */
final class HookInput
{
    /** @param array<mixed> $payload */
    private function __construct(private readonly array $payload)
    {
    }

    public static function fromStdin(): self
    {
        return self::fromString((string) file_get_contents('php://stdin'));
    }

    public static function fromString(string $raw): self
    {
        $decoded = json_decode($raw, true);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function sessionId(): ?string
    {
        $value = $this->payload['session_id'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function toolName(): ?string
    {
        $value = $this->payload['tool_name'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Bare-имя скилла из `tool_input.skill`.
     *
     * Сверено с живым payload Claude Code (2026-07-11): при вызове скилла
     * `tool_name` == "Skill", а имя скилла лежит в `tool_input.skill`
     * (напр. `{"tool_name":"Skill","tool_input":{"skill":"maestro"}}`).
     */
    public function skill(): ?string
    {
        $value = $this->payload['tool_input']['skill'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isSkillTool(): bool
    {
        return $this->toolName() === 'Skill' && $this->skill() !== null;
    }
}
