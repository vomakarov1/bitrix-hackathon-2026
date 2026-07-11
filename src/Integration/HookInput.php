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

    /**
     * Bare-имя скилла из промпта вида `/maestro напиши 1` (UserPromptSubmit).
     *
     * Когда пользователь сам печатает `/скилл`, Claude Code подставляет скилл в
     * промпт напрямую, БЕЗ вызова инструмента Skill — PostToolUse не срабатывает.
     * Поэтому слэш-вызовы ловим на UserPromptSubmit, парся `prompt`.
     * Namespaced-форму `plugin:skill` приводим к bare-имени (часть после `:`).
     */
    public function promptSkill(): ?string
    {
        $prompt = $this->payload['prompt'] ?? null;
        if (!is_string($prompt) || !preg_match('#^/([\w:-]+)#u', ltrim($prompt), $m)) {
            return null;
        }

        $name = $m[1];
        $colon = strrpos($name, ':');

        return $colon === false ? $name : (substr($name, $colon + 1) ?: null);
    }
}
