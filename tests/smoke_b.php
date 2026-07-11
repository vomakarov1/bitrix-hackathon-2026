<?php

declare(strict_types=1);

/**
 * Кейсы блока B (SDD §8). Раннер самодостаточен на Fake* (§2.3); при появлении
 * реальной A владелец файла — A, кейсы B переносятся в конец общего smoke.php.
 *
 * Самодостаточный раннер на голом PHP (PHPUnit не установлен): подключает
 * `vendor/autoload.php`, собирает объекты на `Fake*`, гоняет кейсы §8 под
 * фиксируемым `FakeClock` и временными `TAMAGOTCHI_HOME`/`CLAUDE_CONFIG_DIR`
 * (см. `$tmpRoot` ниже) — реальный конфиг Claude не трогаем. Каждый кейс —
 * `runCase($label, $fn)`; в конце — "PASSED: N, FAILED: M" и ненулевой exit
 * при провале.
 *
 * Часть кейсов (адаптер хуков) запускает `tests/support/hook_fixture.php` в
 * отдельном подпроцессе через `proc_open`: `HookHandlers::handleToolUse()` и
 * `::handleSessionEnd()` читают `php://stdin` напрямую (не инъектируемо), и
 * единственный безопасный детерминированный способ это проверить, не рискуя
 * подвесить раннер на реальном stdin, — управляемый пайп в отдельном процессе.
 */

require __DIR__.'/../vendor/autoload.php';

use Vladislavmakarov\BitrixHackathon2026\App\CharacterService;
use Vladislavmakarov\BitrixHackathon2026\App\Contracts\SessionStoreInterface;
use Vladislavmakarov\BitrixHackathon2026\App\Exception\SkillAlreadyBoundException;
use Vladislavmakarov\BitrixHackathon2026\App\FeedingService;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeCharacterFactory;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeClock;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakePaths;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeRepository;
use Vladislavmakarov\BitrixHackathon2026\App\Stub\FakeSessionStore;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;
use Vladislavmakarov\BitrixHackathon2026\Integration\ErrorLog;
use Vladislavmakarov\BitrixHackathon2026\Integration\HookHandlers;
use Vladislavmakarov\BitrixHackathon2026\Integration\HookInput;
use Vladislavmakarov\BitrixHackathon2026\Integration\HooksInstaller;
use Vladislavmakarov\BitrixHackathon2026\Integration\PetConsoleView;
use Vladislavmakarov\BitrixHackathon2026\Integration\PetListView;
use Vladislavmakarov\BitrixHackathon2026\Integration\SkillCatalog;

// ---------------------------------------------------------------------------
// Мини-харнесс: счётчики, assert-функции, раннер кейсов.
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function describeValue(mixed $value): string
{
    return is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf('%s (ожидалось %s, получено %s)', $message, describeValue($expected), describeValue($actual)));
    }
}

function assertNull(mixed $value, string $message): void
{
    assertTrue($value === null, sprintf('%s (получено %s)', $message, describeValue($value)));
}

function assertNotNull(mixed $value, string $message): void
{
    assertTrue($value !== null, $message);
}

function assertStringContains(string $needle, string $haystack, string $message): void
{
    assertTrue(str_contains($haystack, $needle), sprintf('%s (искали %s в %s)', $message, describeValue($needle), describeValue($haystack)));
}

/** @param callable(): void $fn */
function runCase(string $label, callable $fn): void
{
    global $passed, $failed;

    try {
        $fn();
        ++$passed;
        echo "  PASS  {$label}\n";
    } catch (\Throwable $e) {
        ++$failed;
        echo "  FAIL  {$label}\n        -> {$e->getMessage()}\n";
    }
}

function removeDirRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.'/'.$item;
        if (is_dir($path) && !is_link($path)) {
            removeDirRecursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

/**
 * @return array{clock:FakeClock,repo:FakeRepository,sessions:FakeSessionStore,factory:FakeCharacterFactory,feeding:FeedingService}
 */
function makeFakes(DateTimeImmutable $now): array
{
    $clock = new FakeClock($now);
    $repo = new FakeRepository();
    $sessions = new FakeSessionStore();
    $factory = new FakeCharacterFactory($clock);
    $feeding = new FeedingService($repo, $sessions, $clock);

    return compact('clock', 'repo', 'sessions', 'factory', 'feeding');
}

/**
 * Запускает tests/support/hook_fixture.php в подпроцессе с управляемым stdin
 * (TAMAGOTCHI_HOME/CLAUDE_CONFIG_DIR подпроцесс наследует через putenv() ниже
 * — реальный конфиг Claude не трогаем). Диагностика читается из STDERR
 * (маркер `DIAG_JSON:`), stdout — ровно то, что печатает сам хук.
 *
 * @return array{stdout:string,stderr:string,exitCode:int,diag:array<mixed>}
 */
function runHookFixture(array $scenario, string $stdin): array
{
    $fixture = __DIR__.'/support/hook_fixture.php';
    $cmd = [PHP_BINARY, $fixture, json_encode($scenario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open($cmd, $descriptors, $pipes, __DIR__.'/..');
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить фикстур-процесс hook_fixture.php.');
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $diag = [];
    foreach (explode("\n", $stderr) as $line) {
        if (str_starts_with($line, 'DIAG_JSON:')) {
            $decoded = json_decode(substr($line, strlen('DIAG_JSON:')), true);
            $diag = is_array($decoded) ? $decoded : [];
        }
    }

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exitCode' => $exitCode, 'diag' => $diag];
}

/**
 * Запускает `bin/tamagotchi` в подпроцессе с изолированными TAMAGOTCHI_HOME/
 * CLAUDE_CONFIG_DIR (переданными явно через $env proc_open — не пересекается с
 * глобальными putenv() выше, реальный ~/.claude не трогаем). Fake-репозиторий
 * in-memory и между процессами не сохраняется — этот helper проверяет
 * парсинг аргументов/коды выхода/сообщения роутера, а не персистентность.
 *
 * @param string[] $args
 *
 * @return array{stdout:string,stderr:string,exitCode:int}
 */
function runRouter(array $args, string $home, string $claudeConfigDir): array
{
    $bin = __DIR__.'/../bin/tamagotchi';
    $cmd = array_merge([PHP_BINARY, $bin], $args);
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env = ['TAMAGOTCHI_HOME' => $home, 'CLAUDE_CONFIG_DIR' => $claudeConfigDir, 'PATH' => (string) getenv('PATH')];

    $process = proc_open($cmd, $descriptors, $pipes, __DIR__.'/..', $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить подпроцесс bin/tamagotchi.');
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exitCode' => $exitCode];
}

function section(string $title): void
{
    echo "\n--- {$title} ---\n";
}

// ---------------------------------------------------------------------------
// Временные каталоги (никогда не трогаем реальный конфиг Claude): всё лежит
// под tests/tmp/, чистится в конце прогона независимо от исхода.
// ---------------------------------------------------------------------------

$tmpRoot = __DIR__.'/tmp/smoke-'.bin2hex(random_bytes(4));
if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
    fwrite(STDERR, "Не удалось создать временный каталог {$tmpRoot}\n");
    exit(1);
}
$tmpHome = $tmpRoot.'/home';
$tmpClaudeConfig = $tmpRoot.'/claude-config';
// Подпроцессы hook_fixture.php наследуют эти переменные через putenv() (proc_open
// без явного $env наследует окружение текущего процесса) — временные пути,
// реальный ~/.claude не затрагивается.
putenv('TAMAGOTCHI_HOME='.$tmpHome);
putenv('CLAUDE_CONFIG_DIR='.$tmpClaudeConfig);

register_shutdown_function(static function () use ($tmpRoot): void {
    removeDirRecursive($tmpRoot);
});

echo "Тесты блока B (SDD-B §8)\n";

// =============================================================================
// FeedingService
// =============================================================================

section('FeedingService');

runCase('settle с использованием: satiety += FEED_PER_SESSION + стрик-бонус, lastFedAt проштампован, сессия очищена', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $pet = $factory->create('Рекс', 'demo-skill', null);
    $repo->save($pet);
    $sessions->markSkillUsed('sess-1', 'demo-skill', $now);

    $before = $pet->satiety();
    $feeding->settleSession('sess-1');

    // Первый активный день -> liveStreak(today)==1 -> бонус = min(1, CAP) * STREAK_FEED_STEP.
    $expectedBonus = min(1, Constants::STREAK_BONUS_CAP) * Constants::STREAK_FEED_STEP;
    $expected = min(Constants::MAX_SATIETY, $before + Constants::FEED_PER_SESSION + $expectedBonus);

    assertSame($expected, $pet->satiety(), 'satiety должна учитывать FEED_PER_SESSION + стрик-бонус');
    assertNotNull($pet->toArray()['lastFedAt'], 'lastFedAt должен быть проштампован');
    assertSame([], $sessions->getUsedSkills('sess-1'), 'сессия должна быть очищена после settle');
});

runCase('settle без использования внутри окна STARVE_TIMEOUT_HOURS — голода нет', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $pet = $factory->fromArray([
        'id' => 'p-inside', 'name' => 'Тень', 'skill' => 'idle-skill', 'type' => null,
        'createdAt' => $now->modify('-2 days')->format(DATE_ATOM),
        'satiety' => 50,
        'lastFedAt' => $now->modify('-5 hours')->format(DATE_ATOM), // 5ч < STARVE_TIMEOUT_HOURS=12
    ]);
    $repo->save($pet);
    $sessions->markSkillUsed('sess-2', 'other-skill', $now); // питомец не использован в этой сессии

    $feeding->settleSession('sess-2');

    assertSame(50, $pet->satiety(), 'внутри окна таймаута голода быть не должно');
});

runCase('settle без использования за пределами STARVE_TIMEOUT_HOURS — ровно один STARVE_STEP', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $pet = $factory->fromArray([
        'id' => 'p-outside', 'name' => 'Игла', 'skill' => 'idle-skill-2', 'type' => null,
        'createdAt' => $now->modify('-2 days')->format(DATE_ATOM),
        'satiety' => 50,
        'lastFedAt' => $now->modify('-13 hours')->format(DATE_ATOM), // 13ч >= 12ч -> голодает
    ]);
    $repo->save($pet);
    $sessions->markSkillUsed('sess-3', 'unrelated-skill', $now);

    $feeding->settleSession('sess-3');

    assertSame(50 - Constants::STARVE_STEP, $pet->satiety(), 'должен списаться ровно один STARVE_STEP');
    assertNotNull($pet->toArray()['lastStarvedAt'], 'lastStarvedAt должен быть проштампован');
});

runCase('recordSkillUsage: usageCount++ на каждый вызов, скилл помечен в сессии; без питомца — null без побочных эффектов', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $pet = $factory->create('Байт', 'used-skill', null);
    $repo->save($pet);

    $result1 = $feeding->recordSkillUsage('sess-4', 'used-skill');
    $feeding->recordSkillUsage('sess-4', 'used-skill');

    assertSame($pet, $result1, 'должен вернуть найденного питомца');
    assertSame(2, $pet->usageCount(), 'usageCount должен вырасти на каждый вызов');
    assertTrue(in_array('used-skill', $sessions->getUsedSkills('sess-4'), true), 'скилл должен быть помечен в сессии (updatedAt проштампован)');

    $resultNone = $feeding->recordSkillUsage('sess-5', 'nonexistent-skill');
    assertNull($resultNone, 'без питомца должен вернуться null');
    assertSame([], $sessions->getUsedSkills('sess-5'), 'без питомца сессия не должна быть помечена (нет побочных эффектов)');
});

runCase('effectiveFeed: бонус растёт со стриком (капается STREAK_BONUS_CAP) и итог зажат MAX_SATIETY', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $yesterday = $now->modify('-1 day')->format('Y-m-d');
    $pet = $factory->fromArray([
        'id' => 'p-streak', 'name' => 'Стрик', 'skill' => 'streak-skill', 'type' => null,
        'createdAt' => $now->modify('-30 days')->format(DATE_ATOM),
        'satiety' => 95,
        'streak' => 12, // уже больше STREAK_BONUS_CAP=10 -> бонус должен закапиться
        'bestStreak' => 12,
        'lastActiveDay' => $yesterday,
    ]);
    $repo->save($pet);
    $sessions->markSkillUsed('sess-6', 'streak-skill', $now);

    $feeding->settleSession('sess-6');

    // Стрик 12 -> 13 (продолжение), бонус капается в STREAK_BONUS_CAP(10)*STREAK_FEED_STEP(2)=20;
    // 95 + FEED_PER_SESSION(15) + 20 = 130 -> зажимается в MAX_SATIETY.
    assertSame(Constants::MAX_SATIETY, $pet->satiety(), 'сумма кормёжки+бонуса должна зажиматься MAX_SATIETY');

    // Примечание (§8 SDD-B): "при STREAK_FEED_STEP=0 — бонуса нет" не проверено
    // исполняемым тестом. Domain\Constants — final class с фиксированной
    // STREAK_FEED_STEP=2 (не инжектируется, переопределить константу в PHP без
    // расширений вроде uopz нельзя); форсить через reflection/подмену класса —
    // не в скоупе B6. Поведение при 0 доказывается формулой: `min(...) * 0`
    // тождественно равно 0 при любом стрике — проверено инспекцией кода
    // FeedingService::settleSession (см. src/App/FeedingService.php).
});

runCase('несколько settle разных сессий над одним репозиторием через mutate — апдейты не теряются', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    ['repo' => $repo, 'sessions' => $sessions, 'factory' => $factory, 'feeding' => $feeding] = makeFakes($now);

    $petA = $factory->create('А', 'skill-a', null);
    $petB = $factory->create('Б', 'skill-b', null);
    $petC = $factory->fromArray([
        'id' => 'pet-c', 'name' => 'В', 'skill' => 'skill-c', 'type' => null,
        'createdAt' => $now->modify('-2 days')->format(DATE_ATOM),
        'satiety' => 50,
        'lastFedAt' => $now->modify('-20 hours')->format(DATE_ATOM), // за пределами таймаута
    ]);
    $repo->save($petA);
    $repo->save($petB);
    $repo->save($petC);

    $sessions->markSkillUsed('s1', 'skill-a', $now);
    $sessions->markSkillUsed('s1', 'skill-b', $now);
    $feeding->settleSession('s1'); // кормит A и B; C не использован -> голодает

    $satietyAAfterFirst = $petA->satiety();
    $satietyBAfterFirst = $petB->satiety();
    $satietyCAfterFirst = $petC->satiety();

    assertSame(50 - Constants::STARVE_STEP, $satietyCAfterFirst, 'C должен проголодаться в первом settle (не использован)');

    $sessions->markSkillUsed('s2', 'skill-a', $now);
    $feeding->settleSession('s2'); // кормит только A; тот же $now -> B и C внутри окна, не должны измениться

    assertTrue($petA->satiety() > $satietyAAfterFirst, 'A должен быть докормлен во втором settle (апдейт из первого не потерян)');
    assertSame($satietyBAfterFirst, $petB->satiety(), 'B не тронут вторым settle — состояние после первого mutate сохранено');
    assertSame($satietyCAfterFirst, $petC->satiety(), 'C не должен проголодать повторно в том же окне — состояние после первого mutate сохранено');
});

// =============================================================================
// CharacterService (К6)
// =============================================================================

section('CharacterService');

runCase('create бросает SkillAlreadyBoundException на занятом скилле; успешный create возвращает питомца', function () use ($tmpRoot): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    $clock = new FakeClock($now);
    $repo = new FakeRepository();
    $factory = new FakeCharacterFactory($clock);
    $paths = new FakePaths($tmpRoot.'/cs-claude', $tmpRoot.'/cs-data', $tmpRoot.'/cs-settings.json');
    $catalog = new SkillCatalog($paths);
    $service = new CharacterService($repo, $catalog, $factory);

    $pet = $service->create('Милый', 'unique-skill', 'cat');
    assertSame('Милый', $pet->name(), 'create должен вернуть питомца с заданным именем');
    assertSame('unique-skill', $pet->skill(), 'create должен вернуть питомца с заданным скиллом');

    $threw = false;
    try {
        $service->create('Другой', 'unique-skill', null);
    } catch (SkillAlreadyBoundException) {
        $threw = true;
    }
    assertTrue($threw, 'create должен бросать SkillAlreadyBoundException на уже занятом скилле (К6/К2)');
});

// =============================================================================
// SkillCatalog (К5)
// =============================================================================

section('SkillCatalog');

/**
 * SkillCatalog::skillRoots() читает claudeConfigDir() из PathsInterface и
 * CLAUDE_PROJECT_DIR (fallback getcwd()) напрямую из окружения — оборачиваем
 * каждый кейс восстановлением прежнего значения, чтобы не оставить утечку
 * состояния для остальных кейсов файла.
 */
function withProjectDir(string $projectDir, callable $fn): void
{
    $previous = getenv('CLAUDE_PROJECT_DIR');
    putenv('CLAUDE_PROJECT_DIR='.$projectDir);
    try {
        $fn();
    } finally {
        putenv($previous === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR='.$previous);
    }
}

runCase('all(): bare-имена из двух корней, дедуп пересечения, сортировка, не-каталоги отброшены', function () use ($tmpRoot): void {
    $claudeDir = $tmpRoot.'/sk-claude';
    $projectDir = $tmpRoot.'/sk-project';
    mkdir($claudeDir.'/skills/zeta', 0777, true);
    mkdir($claudeDir.'/skills/alpha', 0777, true);
    mkdir($projectDir.'/.claude/skills/alpha', 0777, true); // пересекается с глобальным alpha -> должен схлопнуться
    mkdir($projectDir.'/.claude/skills/beta', 0777, true);
    file_put_contents($projectDir.'/.claude/skills/not-a-dir.txt', ''); // не каталог -> должен быть отброшен

    withProjectDir($projectDir, function () use ($tmpRoot, $claudeDir): void {
        $paths = new FakePaths($claudeDir, $tmpRoot.'/sk-data', $tmpRoot.'/sk-settings.json');
        $catalog = new SkillCatalog($paths);

        assertSame(['alpha', 'beta', 'zeta'], $catalog->all(), 'должны быть отсортированные bare-имена, дубль alpha схлопнут, файл отброшен');
    });
});

runCase('all(): оба корня skills/ отсутствуют -> пустой список без ошибки', function () use ($tmpRoot): void {
    $claudeDir = $tmpRoot.'/sk-claude-empty';
    $projectDir = $tmpRoot.'/sk-project-empty';
    mkdir($claudeDir, 0777, true);
    mkdir($projectDir, 0777, true);
    // Ни {claudeDir}/skills, ни {projectDir}/.claude/skills не создаём.

    withProjectDir($projectDir, function () use ($tmpRoot, $claudeDir): void {
        $paths = new FakePaths($claudeDir, $tmpRoot.'/sk-data-empty', $tmpRoot.'/sk-settings-empty.json');
        $catalog = new SkillCatalog($paths);

        assertSame([], $catalog->all(), 'без каталогов skills/ в обоих корнях список должен быть пуст, без исключения');
    });
});

runCase('all(): только глобальный корень существует -> частичный список без ошибки', function () use ($tmpRoot): void {
    $claudeDir = $tmpRoot.'/sk-claude-partial';
    $projectDir = $tmpRoot.'/sk-project-partial';
    mkdir($claudeDir.'/skills/only-global', 0777, true);
    mkdir($projectDir, 0777, true); // {projectDir}/.claude/skills не создаём

    withProjectDir($projectDir, function () use ($tmpRoot, $claudeDir): void {
        $paths = new FakePaths($claudeDir, $tmpRoot.'/sk-data-partial', $tmpRoot.'/sk-settings-partial.json');
        $catalog = new SkillCatalog($paths);

        assertSame(['only-global'], $catalog->all(), 'при отсутствии одного из корней должен вернуться частичный список без ошибки');
    });
});

// =============================================================================
// HookInput
// =============================================================================

section('HookInput');

runCase('битый/пустой stdin -> все поля null, isSkillTool()==false', function (): void {
    foreach (['', '   ', 'not-json{{{', 'null', '42', '"str"'] as $raw) {
        $in = HookInput::fromString($raw);
        assertNull($in->sessionId(), "sessionId должен быть null для raw=".describeValue($raw));
        assertNull($in->toolName(), "toolName должен быть null для raw=".describeValue($raw));
        assertNull($in->skill(), "skill должен быть null для raw=".describeValue($raw));
        assertFalse($in->isSkillTool(), "isSkillTool должен быть false для raw=".describeValue($raw));
    }
});

runCase('валидный JSON, но не Skill-инструмент -> isSkillTool()==false', function (): void {
    $in = HookInput::fromString((string) json_encode([
        'session_id' => 's', 'tool_name' => 'Bash', 'tool_input' => ['command' => 'ls'],
    ]));
    assertSame('s', $in->sessionId(), 'sessionId должен парситься из валидного payload');
    assertFalse($in->isSkillTool(), 'не Skill-инструмент -> isSkillTool()==false');
});

runCase('валидный Skill-payload -> isSkillTool()==true, skill() отдаёт bare-имя', function (): void {
    $in = HookInput::fromString((string) json_encode([
        'session_id' => 's2', 'tool_name' => 'Skill', 'tool_input' => ['skill' => 'demo'],
    ]));
    assertTrue($in->isSkillTool(), 'корректный Skill payload -> isSkillTool()==true');
    assertSame('demo', $in->skill(), 'skill() должен вернуть bare-имя из tool_input.skill');
});

// =============================================================================
// HooksInstaller / setup (§9.5)
// =============================================================================

section('HooksInstaller (setup)');

runCase('install идемпотентен: повтор -> одна запись каждого хука, чужие ключи settings.json целы', function () use ($tmpRoot): void {
    $settingsPath = $tmpRoot.'/settings-idempotent.json';
    file_put_contents($settingsPath, (string) json_encode([
        'foreignKey' => ['nested' => true],
        'hooks' => [
            'PostToolUse' => [
                ['matcher' => 'OtherTool', 'hooks' => [['type' => 'command', 'command' => 'echo other']]],
            ],
        ],
    ], JSON_PRETTY_PRINT));

    $paths = new FakePaths($tmpRoot.'/hi-claude', $tmpRoot.'/hi-data', $settingsPath);
    $installer = new HooksInstaller($paths);
    $binPath = (string) realpath(__DIR__.'/../bin/tamagotchi');

    $installer->install($binPath);
    $installer->install($binPath); // второй раз — должен быть no-op

    $settings = json_decode((string) file_get_contents($settingsPath), true);

    assertTrue(($settings['foreignKey']['nested'] ?? null) === true, 'чужой ключ settings.json должен остаться нетронутым');

    $postToolUse = $settings['hooks']['PostToolUse'] ?? [];
    $ourToolEntries = array_filter($postToolUse, static function (array $entry) use ($binPath): bool {
        foreach ($entry['hooks'] ?? [] as $hook) {
            $command = (string) ($hook['command'] ?? '');
            if (str_contains($command, $binPath) && str_contains($command, 'hook:tool-use')) {
                return true;
            }
        }

        return false;
    });
    assertSame(1, count($ourToolEntries), 'должна быть ровно одна запись PostToolUse для нашей команды после двух install()');

    $otherToolEntries = array_filter($postToolUse, static fn (array $entry): bool => ($entry['matcher'] ?? '') === 'OtherTool');
    assertSame(1, count($otherToolEntries), 'чужая запись PostToolUse (matcher=OtherTool) должна остаться на месте');

    $sessionEnd = $settings['hooks']['SessionEnd'] ?? [];
    assertSame(1, count($sessionEnd), 'должна быть ровно одна запись SessionEnd после двух install()');

    assertTrue(is_dir($tmpRoot.'/hi-data'), 'install должен гарантировать dataDir');
});

runCase('install на битом settings.json -> исключение, файл не тронут (§9.5)', function () use ($tmpRoot): void {
    $settingsPath = $tmpRoot.'/settings-corrupted.json';
    $corrupted = '{not valid json,,,';
    file_put_contents($settingsPath, $corrupted);

    $paths = new FakePaths($tmpRoot.'/hi-claude-2', $tmpRoot.'/hi-data-2', $settingsPath);
    $installer = new HooksInstaller($paths);

    $threw = false;
    try {
        $installer->install((string) realpath(__DIR__.'/../bin/tamagotchi'));
    } catch (\Throwable) {
        $threw = true;
    }

    assertTrue($threw, 'install должен бросать исключение на битом JSON');
    assertSame($corrupted, file_get_contents($settingsPath), 'файл settings.json не должен быть изменён при битом JSON');
});

// =============================================================================
// Консоль питомца (PetConsoleView + HookHandlers)
// =============================================================================

section('Консоль (PetConsoleView / HookHandlers)');

runCase('PetConsoleView.render: карточка непуста и содержит ключевые поля', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    $clock = new FakeClock($now);
    $factory = new FakeCharacterFactory($clock);
    $pet = $factory->create('Пикси', 'render-skill', null);

    $view = new PetConsoleView();
    $card = $view->render($pet, $clock->today());

    assertTrue($card !== '', 'карточка не должна быть пустой строкой');
    assertStringContains('Пикси', $card, 'карточка должна содержать имя питомца');
    assertStringContains('покормлен!', $card, 'карточка должна содержать заголовок (§9.2 OQ-N3)');
    assertStringContains((string) $pet->satiety(), $card, 'карточка должна содержать текущую сытость');
});

runCase('hook:tool-use (подпроцесс) с привязанным питомцем: pruneExpired сработал, usageCount вырос, печатается непустой JSON-конверт карточки', function (): void {
    $now = '2026-07-11T10:00:00+00:00';
    $scenario = [
        'mode' => 'tool-use',
        'now' => $now,
        'pets' => [[
            'id' => 'pet-tu-1', 'name' => 'Клод', 'skill' => 'tool-use-skill', 'type' => null,
            'createdAt' => $now, 'satiety' => 50,
        ]],
        'sessionSeeds' => [
            ['sessionId' => 'stale-tu', 'skill' => 'irrelevant', 'updatedAt' => '2026-07-10T00:00:00+00:00'], // >SESSION_TTL_HOURS(24) назад
        ],
        'checkSessions' => ['stale-tu', 'sess-tu'],
    ];
    $stdin = (string) json_encode(['session_id' => 'sess-tu', 'tool_name' => 'Skill', 'tool_input' => ['skill' => 'tool-use-skill']]);

    $result = runHookFixture($scenario, $stdin);

    assertSame(0, $result['exitCode'], 'handleToolUse всегда возвращает 0 (Р4)');
    assertTrue($result['stdout'] !== '', 'на успех должна печататься непустая карточка');

    $envelope = json_decode($result['stdout'], true);
    assertTrue(is_array($envelope), 'вывод хука должен быть валидным JSON-конвертом (§4.6.1)');
    assertStringContains('Клод', (string) ($envelope['systemMessage'] ?? ''), 'systemMessage должен содержать карточку с именем питомца');
    assertStringContains('Клод', (string) ($envelope['hookSpecificOutput']['additionalContext'] ?? ''), 'additionalContext должен содержать карточку');

    $diag = $result['diag'];
    assertTrue(in_array('irrelevant', $diag['before']['stale-tu'] ?? [], true), 'просроченная сессия должна существовать до pruneExpired');
    assertSame([], $diag['after']['stale-tu'] ?? ['непусто'], 'просроченная сессия должна быть удалена pruneExpired на старте handleToolUse');
    assertTrue(in_array('tool-use-skill', $diag['after']['sess-tu'] ?? [], true), 'markSkillUsed должен пометить скилл в текущей сессии (updatedAt проштампован)');

    $petAfter = null;
    foreach ($diag['pets'] ?? [] as $p) {
        if (($p['id'] ?? null) === 'pet-tu-1') {
            $petAfter = $p;
        }
    }
    assertNotNull($petAfter, 'питомец должен присутствовать в репозитории после вызова');
    assertSame(1, $petAfter['usageCount'] ?? null, 'usageCount должен вырасти на 1 после recordSkillUsage');
});

runCase('hook:tool-use (подпроцесс) без привязанного питомца: stdout пуст, exit 0', function (): void {
    $now = '2026-07-11T10:00:00+00:00';
    $scenario = [
        'mode' => 'tool-use',
        'now' => $now,
        'pets' => [],
        'sessionSeeds' => [],
        'checkSessions' => [],
    ];
    $stdin = (string) json_encode(['session_id' => 'sess-none', 'tool_name' => 'Skill', 'tool_input' => ['skill' => 'no-such-skill']]);

    $result = runHookFixture($scenario, $stdin);

    assertSame(0, $result['exitCode'], 'handleToolUse всегда возвращает 0, даже без питомца');
    assertSame('', $result['stdout'], 'без питомца stdout должен быть пуст (карточка не печатается)');
});

runCase('handleToolUse: исключение в pruneExpired -> exit 0, ошибка в лог-файл (не в stderr)', function () use ($tmpRoot): void {
    // pruneExpired бросает раньше, чем HookHandlers дойдёт до HookInput::fromStdin() —
    // проверяем в текущем процессе напрямую (реальный php://stdin здесь безопасно
    // не читается вовсе, см. src/Integration/HookHandlers.php).
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    $clock = new FakeClock($now);
    $paths = new FakePaths($tmpRoot.'/hh-claude', $tmpRoot.'/hh-data', $tmpRoot.'/hh-settings.json');
    $repo = new FakeRepository();
    $throwingSessions = new class implements SessionStoreInterface {
        public function markSkillUsed(string $sessionId, string $skill, \DateTimeImmutable $now): void
        {
        }

        public function getUsedSkills(string $sessionId): array
        {
            return [];
        }

        public function clear(string $sessionId): void
        {
        }

        public function pruneExpired(int $ttlHours, \DateTimeImmutable $now): void
        {
            throw new \RuntimeException('forced-for-smoke-test');
        }
    };
    $feeding = new FeedingService($repo, $throwingSessions, $clock);
    $view = new PetConsoleView();
    $log = new ErrorLog($paths);
    $hooks = new HookHandlers($feeding, $throwingSessions, $view, $clock, $log);

    $code = $hooks->handleToolUse();

    assertSame(0, $code, 'handleToolUse должен вернуть 0 даже при внутреннем исключении (Р4)');
    $logPath = $paths->dataDir().'/hook-errors.log';
    assertTrue(is_file($logPath), 'ошибка должна быть записана в лог-файл');
    assertStringContains('tool-use', (string) file_get_contents($logPath), 'запись лога должна быть помечена местом возникновения (tool-use)');
});

runCase('hook:session-end (подпроцесс): pruneExpired + settleSession отработали, сессия очищена, stdout пуст', function (): void {
    $now = '2026-07-11T10:00:00+00:00';
    $scenario = [
        'mode' => 'session-end',
        'now' => $now,
        'pets' => [[
            'id' => 'pet-se-1', 'name' => 'Ася', 'skill' => 'se-skill', 'type' => null,
            'createdAt' => $now, 'satiety' => 50,
        ]],
        'sessionSeeds' => [
            ['sessionId' => 'sess-se', 'skill' => 'se-skill', 'updatedAt' => $now],
            ['sessionId' => 'stale-se', 'skill' => 'irrelevant', 'updatedAt' => '2026-07-10T00:00:00+00:00'],
        ],
        'checkSessions' => ['sess-se', 'stale-se'],
    ];
    $stdin = (string) json_encode(['session_id' => 'sess-se']);

    $result = runHookFixture($scenario, $stdin);

    assertSame(0, $result['exitCode'], 'handleSessionEnd всегда возвращает 0 (Р4)');
    assertSame('', $result['stdout'], 'session-end никогда ничего не печатает в stdout');

    $diag = $result['diag'];
    assertTrue(in_array('irrelevant', $diag['before']['stale-se'] ?? [], true), 'просроченная сессия должна существовать до pruneExpired');
    assertSame([], $diag['after']['stale-se'] ?? ['непусто'], 'просроченная сессия должна быть удалена pruneExpired на старте handleSessionEnd');
    assertSame([], $diag['after']['sess-se'] ?? ['непусто'], 'обработанная сессия должна быть очищена settleSession');

    $petAfter = null;
    foreach ($diag['pets'] ?? [] as $p) {
        if (($p['id'] ?? null) === 'pet-se-1') {
            $petAfter = $p;
        }
    }
    assertNotNull($petAfter, 'питомец должен присутствовать в репозитории после вызова');
    assertTrue(($petAfter['satiety'] ?? 0) > 50, 'питомец должен быть покормлен по итогам settleSession');
});

// =============================================================================
// PetListView (B5)
// =============================================================================

section('PetListView');

runCase('renderList: пустой список -> пустая строка', function (): void {
    $view = new PetListView();

    assertSame('', $view->renderList([], '2026-07-11'), 'пустой список питомцев должен рендериться в пустую строку');
});

runCase('renderList: один питомец -> строка "{stage} {name} · {satiety}/{max} {mood} · {streak} · id:{id}"', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    $today = $now->format('Y-m-d');
    $clock = new FakeClock($now);
    $factory = new FakeCharacterFactory($clock);
    $pet = $factory->create('Кекс', 'demo-skill', null); // satiety=INITIAL_SATIETY(50), без активных дней -> стрик оборван

    $view = new PetListView();
    $output = $view->renderList([$pet], $today);

    $expected = sprintf('🥚 Кекс · 50/%d 🙂 · 💤 стрик оборван · id:%s'."\n", Constants::MAX_SATIETY, $pet->id());
    assertSame($expected, $output, 'формат строки списка должен совпадать с ожидаемым (стадия/сытость/настроение/стрик/id)');
});

runCase('renderList: несколько питомцев -> по строке на каждого, порядок сохранён, id виден, рекорд стрика отмечен', function (): void {
    $now = new DateTimeImmutable('2026-07-11T10:00:00+00:00');
    $today = $now->format('Y-m-d');
    $clock = new FakeClock($now);
    $factory = new FakeCharacterFactory($clock);

    $petA = $factory->create('Ася', 'skill-a', null); // satiety=50, без активных дней
    $petB = $factory->fromArray([
        'id' => 'pet-b-streak', 'name' => 'Борис', 'skill' => 'skill-b', 'type' => null,
        'createdAt' => $now->modify('-10 days')->format(DATE_ATOM),
        'satiety' => 90,
        'lastActiveDay' => $today, 'streak' => 5, 'bestStreak' => 5,
    ]);

    $view = new PetListView();
    $lines = explode("\n", rtrim($view->renderList([$petA, $petB], $today), "\n"));

    assertSame(2, count($lines), 'должна быть ровно одна строка на каждого питомца');
    assertStringContains('Ася', $lines[0], 'первая строка соответствует первому питомцу (порядок сохранён)');
    assertStringContains('id:'.$petA->id(), $lines[0], 'id первого питомца должен быть виден');
    assertStringContains('Борис', $lines[1], 'вторая строка соответствует второму питомцу (порядок сохранён)');
    assertStringContains('90/', $lines[1], 'сытость второго питомца должна быть видна');
    assertStringContains('😋', $lines[1], 'высокая сытость -> довольное настроение');
    assertStringContains('🔥 5 дн. ✨', $lines[1], 'личный рекорд стрика (streak==bestStreak, >=STREAK_RECORD_MIN_DAYS) должен отмечаться ✨');
    assertStringContains('id:'.$petB->id(), $lines[1], 'id второго питомца должен быть виден');
});

// =============================================================================
// argv-роутер bin/tamagotchi (B5)
// =============================================================================

section('Роутер bin/tamagotchi');

runCase('router: create --skill=x --name=Y -> exit 0, сообщение об успехе с именем и скиллом', function () use ($tmpRoot): void {
    $home = $tmpRoot.'/router-create-ok/home';
    $claudeConfig = $tmpRoot.'/router-create-ok/claude-config';

    $result = runRouter(['create', '--skill=demo-skill', '--name=Крош'], $home, $claudeConfig);

    assertSame(0, $result['exitCode'], 'create с именем и скиллом должен завершаться exit 0');
    assertStringContains('Крош', $result['stdout'], 'сообщение об успехе должно содержать имя питомца');
    assertStringContains('demo-skill', $result['stdout'], 'сообщение об успехе должно содержать скилл');
});

runCase('router: create --skill=x без --name -> exit != 0, подсказка про имя (§9.2 OQ-N1)', function () use ($tmpRoot): void {
    $home = $tmpRoot.'/router-create-noname/home';
    $claudeConfig = $tmpRoot.'/router-create-noname/claude-config';

    $result = runRouter(['create', '--skill=demo-skill'], $home, $claudeConfig);

    assertTrue($result['exitCode'] !== 0, 'create без --name должен завершаться ненулевым кодом');
    assertStringContains('имя', $result['stderr'], 'сообщение об ошибке должно подсказывать про имя питомца');
});

runCase('router: delete без id -> exit != 0, сообщение про необходимость id', function () use ($tmpRoot): void {
    $home = $tmpRoot.'/router-delete-noid/home';
    $claudeConfig = $tmpRoot.'/router-delete-noid/claude-config';

    $result = runRouter(['delete'], $home, $claudeConfig);

    assertTrue($result['exitCode'] !== 0, 'delete без id должен завершаться ненулевым кодом');
    assertStringContains('id', $result['stderr'], 'сообщение об ошибке должно упоминать необходимость id');
});

runCase('router: help -> exit 0, справка перечисляет команды', function () use ($tmpRoot): void {
    $home = $tmpRoot.'/router-help/home';
    $claudeConfig = $tmpRoot.'/router-help/claude-config';

    $result = runRouter(['help'], $home, $claudeConfig);

    assertSame(0, $result['exitCode'], 'help должен завершаться exit 0');
    assertStringContains('tamagotchi', $result['stdout'], 'справка должна упоминать команду верхнего уровня');
    assertStringContains('create', $result['stdout'], 'справка должна перечислять команду create');
});

// =============================================================================
// php -l sweep по всем файлам блока B
// =============================================================================

section('php -l sweep');

runCase('php -l: все .php файлы src/** и bin/tamagotchi синтаксически чисты', function (): void {
    $root = __DIR__.'/..';
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    $files[] = $root.'/bin/tamagotchi';

    assertTrue(count($files) > 0, 'должен быть хотя бы один файл для проверки');

    $failures = [];
    foreach ($files as $file) {
        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);
        $text = implode("\n", $output);
        if ($code !== 0 || !str_contains($text, 'No syntax errors detected')) {
            $failures[] = $file.': '.$text;
        }
    }

    assertSame([], $failures, 'все файлы должны быть синтаксически чисты: '.implode('; ', $failures));
});

// =============================================================================
// Итог
// =============================================================================

echo "\n";
printf("PASSED: %d, FAILED: %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
