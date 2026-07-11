<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\SessionStoreInterface;
use Vladislavmakarov\BitrixHackathon2026\App\FeedingService;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;

/**
 * Обёртка хуков `hook:tool-use`/`hook:session-end` (§4.6 SDD-B). Гарант Р4
 * ADR «хук не роняет агента»: try/catch на весь корпус, всегда `exit 0`,
 * ошибки — только в лог. Карточку питомца на успешный tool-use рендерит
 * `PetConsoleView.render`, которую он вызывает.
 */
final class HookHandlers
{
    public function __construct(
        private readonly FeedingService $feeding,
        private readonly SessionStoreInterface $sessions,
        private readonly PetConsoleView $view,
        private readonly ClockInterface $clock,
        private readonly ErrorLog $log,
    ) {
    }

    public function handleToolUse(): int
    {
        try {
            $this->sessions->pruneExpired(Constants::SESSION_TTL_HOURS, $this->clock->now());

            $in = HookInput::fromStdin();
            if (!$in->isSkillTool()) {
                return 0;
            }

            $sessionId = $in->sessionId();
            if ($sessionId === null) {
                return 0;
            }

            $pet = $this->feeding->recordSkillUsage($sessionId, (string) $in->skill());
            if ($pet !== null) {
                $card = $this->view->render($pet, $this->clock->today());
                fwrite(STDOUT, $this->emitCard($card));
            }

            return 0;
        } catch (\Throwable $e) {
            $this->log->write('tool-use', $e);

            return 0;
        }
    }

    public function handleSessionEnd(): int
    {
        try {
            $this->sessions->pruneExpired(Constants::SESSION_TTL_HOURS, $this->clock->now());

            $in = HookInput::fromStdin();
            $sessionId = $in->sessionId();
            if ($sessionId !== null) {
                $this->feeding->settleSession($sessionId);
            }

            return 0;
        } catch (\Throwable $e) {
            $this->log->write('session-end', $e);

            return 0;
        }
    }

    /**
     * §4.6.1: JSON-конверт хука — карточка попадает и в контекст Claude
     * (`hookSpecificOutput.additionalContext`), и в кандидат на прямой показ
     * пользователю (`systemMessage`). Финальный выбор поля — по живому чеку B6.
     */
    private function emitCard(string $card): string
    {
        return json_encode([
            'systemMessage' => $card,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => $card,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
