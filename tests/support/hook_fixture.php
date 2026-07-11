<?php

declare(strict_types=1);

/**
 * Фикстур-процесс для подпроцессных кейсов hook-адаптера (используется только
 * `tests/smoke.php`, §8 SDD-B). `HookHandlers::handleToolUse/handleSessionEnd`
 * читают `php://stdin` напрямую — единственный надёжный способ детерминированно
 * это проверить (не подвесив раннер на реальном stdin) — отдельный процесс,
 * куда родитель через `proc_open` пишет управляемый payload и закрывает канал.
 *
 * Аргумент argv[1] — JSON-сценарий: {mode, now, pets[], sessionSeeds[],
 * checkSessions[]}. Строит граф на Fake* (§2.3) с `FakeClock`, зафиксированным
 * на `now` из сценария — детерминизм по времени.
 *
 * Контракт вывода: STDOUT — ровно то, что печатает сам хук (карточка/пусто),
 * ничего своего сюда не пишем. Диагностика (состояние сессий до/после,
 * состояние репозитория, содержимое hook-errors.log) уходит в STDERR одной
 * строкой с префиксом `DIAG_JSON:`, чтобы не смешиваться со stdout хука.
 */

require __DIR__.'/../../vendor/autoload.php';

use Vladislavmakarov\BitrixHackathon2026\App\FeedingService;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeCharacterFactory;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeClock;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakePaths;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeRepository;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeSessionStore;
use Vladislavmakarov\BitrixHackathon2026\Integration\ErrorLog;
use Vladislavmakarov\BitrixHackathon2026\Integration\HookHandlers;
use Vladislavmakarov\BitrixHackathon2026\Integration\PetConsoleView;

$scenario = json_decode((string) ($argv[1] ?? '{}'), true);
if (!is_array($scenario)) {
    $scenario = [];
}

$now = new DateTimeImmutable((string) ($scenario['now'] ?? 'now'));
$clock = new FakeClock($now);
// Без явных аргументов -> FakePaths читает TAMAGOTCHI_HOME/CLAUDE_CONFIG_DIR из
// окружения подпроцесса (родитель их выставляет через proc_open, §2.3/§8).
$paths = new FakePaths();
$repo = new FakeRepository();
$factory = new FakeCharacterFactory($clock);

foreach ($scenario['pets'] ?? [] as $petData) {
    $repo->save($factory->fromArray($petData));
}

$sessions = new FakeSessionStore();
foreach ($scenario['sessionSeeds'] ?? [] as $seed) {
    $sessions->markSkillUsed(
        (string) $seed['sessionId'],
        (string) $seed['skill'],
        new DateTimeImmutable((string) $seed['updatedAt']),
    );
}

$feeding = new FeedingService($repo, $sessions, $clock);
$view = new PetConsoleView();
$log = new ErrorLog($paths);
$hooks = new HookHandlers($feeding, $sessions, $view, $clock, $log);

$before = [];
foreach ($scenario['checkSessions'] ?? [] as $sid) {
    $before[$sid] = $sessions->getUsedSkills($sid);
}

$mode = (string) ($scenario['mode'] ?? 'tool-use');
$exitCode = $mode === 'session-end' ? $hooks->handleSessionEnd() : $hooks->handleToolUse();

$after = [];
foreach ($scenario['checkSessions'] ?? [] as $sid) {
    $after[$sid] = $sessions->getUsedSkills($sid);
}

$logPath = $paths->dataDir().'/hook-errors.log';

$diag = [
    'exitCode' => $exitCode,
    'before' => $before,
    'after' => $after,
    'pets' => array_map(static fn ($p) => $p->toArray(), $repo->all()),
    'errorLog' => is_file($logPath) ? file_get_contents($logPath) : null,
];
fwrite(STDERR, 'DIAG_JSON:'.json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

exit($exitCode);
