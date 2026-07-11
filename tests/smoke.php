<?php

declare(strict_types=1);

/**
 * Smoke-каркас ФАЗЫ 1.
 *
 * Запуск: php tests/smoke.php
 * Exit-код 0 при всех пройденных ассертах, != 0 при любом провале.
 *
 * НЕ трогает реальный ~/.claude — все env-override указывают во временный
 * каталог из sys_get_temp_dir(), созданный до запуска и удалённый после.
 */

require __DIR__ . '/../vendor/autoload.php';

use Vladislavmakarov\BitrixHackathon2026\Domain\Character;
use Vladislavmakarov\BitrixHackathon2026\Domain\Constants;
use Vladislavmakarov\BitrixHackathon2026\Domain\Stage;
use Vladislavmakarov\BitrixHackathon2026\Storage\CharacterRepository;
use Vladislavmakarov\BitrixHackathon2026\Storage\Paths;
use Vladislavmakarov\BitrixHackathon2026\Storage\SessionStore;
use Vladislavmakarov\BitrixHackathon2026\Storage\SystemClock;
use Vladislavmakarov\BitrixHackathon2026\Tests\FixedClock;

$failures = 0;
$total = 0;

/**
 * Минимальный ассерт-хелпер: не завершает процесс сразу, чтобы временный
 * каталог гарантированно был удалён в конце скрипта.
 */
function assertTrue(bool $condition, string $message): void
{
    global $failures, $total;

    $total++;

    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        $failures++;
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    $expectedStr = is_scalar($expected) ? (string) $expected : json_encode($expected);
    $actualStr = is_scalar($actual) ? (string) $actual : json_encode($actual);
    assertTrue($expected === $actual, "{$message} (expected={$expectedStr}, actual={$actualStr})");
}

function recursiveRemove(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            recursiveRemove($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

// --- Временное окружение (НЕ трогает реальный ~/.claude) ---

$tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tamagotchi_smoke_' . uniqid();
$tempClaudeConfigDir = $tempRoot . DIRECTORY_SEPARATOR . 'claude-config';
$tempTamagotchiHome = $tempRoot . DIRECTORY_SEPARATOR . 'tamagotchi-home';

mkdir($tempClaudeConfigDir, 0777, true);
mkdir($tempTamagotchiHome, 0777, true);

putenv('CLAUDE_CONFIG_DIR=' . $tempClaudeConfigDir);
putenv('TAMAGOTCHI_HOME=' . $tempTamagotchiHome);

try {
    // --- Clock (К7): SystemClock и FixedClock ---

    $systemClock = new SystemClock();
    assertTrue($systemClock->now() instanceof \DateTimeImmutable, 'SystemClock::now() возвращает DateTimeImmutable');
    assertTrue((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $systemClock->today()), 'SystemClock::today() соответствует YYYY-MM-DD');

    $fixedNow = new \DateTimeImmutable('2026-01-15 12:00:00');
    $fixedClock = new FixedClock($fixedNow, '2026-01-15');
    assertSame($fixedNow, $fixedClock->now(), 'FixedClock::now() возвращает заданный момент');
    assertSame('2026-01-15', $fixedClock->today(), 'FixedClock::today() возвращает заданную дату');

    // --- Paths (К4): env-override указывают во временный каталог ---

    $paths = new Paths();

    assertSame($tempClaudeConfigDir, $paths->claudeConfigDir(), 'Paths::claudeConfigDir() честно берёт CLAUDE_CONFIG_DIR');
    assertSame($tempTamagotchiHome, $paths->dataDir(), 'Paths::dataDir() честно берёт TAMAGOTCHI_HOME');
    assertSame(
        $tempClaudeConfigDir . DIRECTORY_SEPARATOR . 'settings.json',
        $paths->settingsPath(),
        'Paths::settingsPath() = claudeConfigDir/settings.json'
    );

    $realHomeClaudeFragment = DIRECTORY_SEPARATOR . '.claude';
    assertTrue(
        !str_ends_with($paths->dataDir(), $realHomeClaudeFragment . DIRECTORY_SEPARATOR . 'tamagotchi'),
        'Paths::dataDir() НЕ указывает в реальный ~/.claude/tamagotchi'
    );

    // --- Stage: граничные значения bestStreak ---

    assertSame(Stage::Egg, Stage::fromBestStreak(0), 'bestStreak=0 → Stage::Egg');
    assertSame('🥚', Stage::fromBestStreak(0)->emoji(), 'Stage::Egg эмодзи 🥚');

    assertSame(Stage::Hatchling, Stage::fromBestStreak(Constants::STAGE_HATCHLING_AT), 'bestStreak=STAGE_HATCHLING_AT → Stage::Hatchling');
    assertSame('🐣', Stage::fromBestStreak(Constants::STAGE_HATCHLING_AT)->emoji(), 'Stage::Hatchling эмодзи 🐣');

    assertSame(Stage::Beast, Stage::fromBestStreak(Constants::STAGE_BEAST_AT), 'bestStreak=STAGE_BEAST_AT → Stage::Beast');
    assertSame('🦊', Stage::fromBestStreak(Constants::STAGE_BEAST_AT)->emoji(), 'Stage::Beast эмодзи 🦊');

    assertSame(Stage::Legend, Stage::fromBestStreak(Constants::STAGE_LEGEND_AT), 'bestStreak=STAGE_LEGEND_AT → Stage::Legend');
    assertSame('🐉', Stage::fromBestStreak(Constants::STAGE_LEGEND_AT)->emoji(), 'Stage::Legend эмодзи 🐉');

    // Значения чуть ниже порогов — предыдущая стадия.
    assertSame(Stage::Egg, Stage::fromBestStreak(Constants::STAGE_HATCHLING_AT - 1), 'bestStreak=STAGE_HATCHLING_AT-1 → Stage::Egg');
    assertSame(Stage::Hatchling, Stage::fromBestStreak(Constants::STAGE_BEAST_AT - 1), 'bestStreak=STAGE_BEAST_AT-1 → Stage::Hatchling');
    assertSame(Stage::Beast, Stage::fromBestStreak(Constants::STAGE_LEGEND_AT - 1), 'bestStreak=STAGE_LEGEND_AT-1 → Stage::Beast');

    // --- CharacterRepository/SessionStore (К2–К3): поведение на пустом хранилище ---
    // (Обе реализованы по-настоящему в ФАЗЕ 3 — JSON-хранилища characters.json/
    // sessions.json с RMW-дисциплиной через flock(). Здесь проверяется поведение
    // при отсутствии данных: неизвестный id/skill/session корректно даёт null/[]
    // без побочных эффектов. Полный CRUD — в кейсах P3.T1/P3.T2 в конце файла.)

    $repo = new CharacterRepository($tempTamagotchiHome);
    assertSame(null, $repo->findById('any-id'), 'CharacterRepository::findById() на пустом хранилище — возвращает null');
    assertSame(null, $repo->findBySkill('any-skill'), 'CharacterRepository::findBySkill() на пустом хранилище — возвращает null');
    assertSame([], $repo->all(), 'CharacterRepository::all() на пустом хранилище — возвращает []');

    $sessionStore = new SessionStore($tempTamagotchiHome);
    assertSame([], $sessionStore->getUsedSkills('any-session'), 'SessionStore::getUsedSkills() для неизвестной сессии — возвращает []');

    // ============================================================
    // ФАЗА 2: P2.T1 — сытость, настроение, уровень, счётчик использований
    // ============================================================

    $now1 = new \DateTimeImmutable('2026-02-01 10:00:00');
    $c1 = Character::create('Neo', 'php', null, $now1);

    assertSame(Constants::INITIAL_SATIETY, $c1->satiety(), 'create(): satiety == INITIAL_SATIETY');

    $c1->feed(1000, $now1);
    assertSame(Constants::MAX_SATIETY, $c1->satiety(), 'feed(): клампится в MAX_SATIETY при большом amount');

    $c1->starve(1000, $now1);
    assertSame(0, $c1->satiety(), 'starve(): клампится в 0 при большом amount');

    $c1->feed(50, $now1);
    assertSame(50, $c1->satiety(), 'feed(): нормальное прибавление без клампа');

    // --- mood() на всех 4 диапазонах ---

    $moodSatisfied = Character::fromArray([
        'id' => 'm1', 'name' => 'A', 'skill' => 'a', 'createdAt' => $now1->format(DATE_ATOM),
        'satiety' => Constants::MOOD_SATISFIED_AT,
    ]);
    assertSame('😋 сыт', $moodSatisfied->mood(), 'mood(): satiety == MOOD_SATISFIED_AT → сыт');

    $moodOk = Character::fromArray([
        'id' => 'm2', 'name' => 'A', 'skill' => 'a', 'createdAt' => $now1->format(DATE_ATOM),
        'satiety' => Constants::MOOD_OK_AT,
    ]);
    assertSame('🙂 норм', $moodOk->mood(), 'mood(): satiety == MOOD_OK_AT → норм');

    $moodHungry = Character::fromArray([
        'id' => 'm3', 'name' => 'A', 'skill' => 'a', 'createdAt' => $now1->format(DATE_ATOM),
        'satiety' => Constants::MOOD_HUNGRY_AT,
    ]);
    assertSame('😟 голоден', $moodHungry->mood(), 'mood(): satiety == MOOD_HUNGRY_AT → голоден');

    $moodStarving = Character::fromArray([
        'id' => 'm4', 'name' => 'A', 'skill' => 'a', 'createdAt' => $now1->format(DATE_ATOM),
        'satiety' => Constants::MOOD_HUNGRY_AT - 1,
    ]);
    assertSame('😫 очень голоден', $moodStarving->mood(), 'mood(): satiety < MOOD_HUNGRY_AT → очень голоден');

    // --- recordUsage растит usageCount, level() растёт по LEVEL_STEP ---

    $c2 = Character::create('Trinity', 'js', null, $now1);
    assertSame(0, $c2->usageCount(), 'create(): usageCount == 0');
    assertSame(0, $c2->level(), 'level(): usageCount 0 → level 0');

    for ($i = 0; $i < Constants::LEVEL_STEP; $i++) {
        $c2->recordUsage();
    }
    assertSame(Constants::LEVEL_STEP, $c2->usageCount(), 'recordUsage(): usageCount растёт на каждый вызов');
    assertSame(1, $c2->level(), 'level(): usageCount == LEVEL_STEP → level 1');

    for ($i = 0; $i < Constants::LEVEL_STEP - 1; $i++) {
        $c2->recordUsage();
    }
    assertSame(1, $c2->level(), 'level(): usageCount == 2*LEVEL_STEP-1 → level ещё 1 (floor)');

    // --- feed/starve штампуют время переданным $now ---

    $creationTime = new \DateTimeImmutable('2026-02-01 00:00:00');
    $c3 = Character::create('Morpheus', 'go', null, $creationTime);
    $feedTime = new \DateTimeImmutable('2026-02-01 08:00:00');
    $c3->feed(10, $feedTime);

    $withinWindowAfterFeed = $feedTime->modify('+' . (Constants::STARVE_TIMEOUT_HOURS - 1) . ' hours');
    $overWindowAfterFeed = $feedTime->modify('+' . (Constants::STARVE_TIMEOUT_HOURS + 1) . ' hours');

    assertTrue(!$c3->isStarvable($withinWindowAfterFeed), 'feed(): lastFedAt штампуется переданным $now (isStarvable ещё false внутри окна)');
    assertTrue($c3->isStarvable($overWindowAfterFeed), 'feed(): lastFedAt штампуется переданным $now (isStarvable true за окном)');

    // ============================================================
    // P2.T2 — стрик по дням: registerActiveDay / liveStreak
    // ============================================================

    $s1 = Character::create('Streaker', 'py', null, $now1);

    $s1->registerActiveDay('2026-03-01');
    assertSame(1, $s1->streak(), 'registerActiveDay(): день 1 → streak == 1');
    assertSame(1, $s1->bestStreak(), 'registerActiveDay(): день 1 → bestStreak == 1');

    $s1->registerActiveDay('2026-03-02');
    $s1->registerActiveDay('2026-03-03');
    assertSame(3, $s1->streak(), 'registerActiveDay(): дни 1-2-3 подряд → streak == 3');
    assertSame(3, $s1->bestStreak(), 'registerActiveDay(): дни 1-2-3 подряд → bestStreak == 3');

    // повторный вызов в тот же день не мультиплицирует
    $s1->registerActiveDay('2026-03-03');
    assertSame(3, $s1->streak(), 'registerActiveDay(): повторный вызов в тот же день не меняет streak');
    assertSame(3, $s1->bestStreak(), 'registerActiveDay(): повторный вызов в тот же день не меняет bestStreak');

    // разрыв: день 3 (2026-03-03), потом день 5 (пропуск дня 4) → streak сброшен в 1, bestStreak сохранён
    $s1->registerActiveDay('2026-03-05');
    assertSame(1, $s1->streak(), 'registerActiveDay(): разрыв в днях → streak сброшен в 1');
    assertSame(3, $s1->bestStreak(), 'registerActiveDay(): разрыв в днях → bestStreak сохраняет максимум');

    // liveStreak: сегодня/вчера — не протух; протухает при разрыве 2+ дня
    assertSame(1, $s1->liveStreak('2026-03-05'), 'liveStreak(): today == lastUsedDate → возвращает streak');
    assertSame(1, $s1->liveStreak('2026-03-06'), 'liveStreak(): today == lastUsedDate+1 (вчера) → возвращает streak');
    assertSame(0, $s1->liveStreak('2026-03-07'), 'liveStreak(): пропуск 2+ дней → 0');
    assertSame(0, $s1->liveStreak('2026-03-10'), 'liveStreak(): пропуск много дней → 0');

    // liveStreak — чистое чтение, не меняет состояние
    assertSame(1, $s1->streak(), 'liveStreak(): не мутирует streak');
    assertSame(3, $s1->bestStreak(), 'liveStreak(): не мутирует bestStreak');

    // ============================================================
    // P2.T3 — стадия эволюции и голодаемость по таймауту
    // ============================================================

    $stageChar = Character::create('Stager', 'rb', null, $now1);
    assertSame(Stage::Egg, $stageChar->stage(), 'stage(): bestStreak == 0 → Stage::Egg');

    foreach (range(1, Constants::STAGE_HATCHLING_AT) as $day) {
        $stageChar->registerActiveDay(sprintf('2026-04-%02d', $day));
    }
    assertSame(Constants::STAGE_HATCHLING_AT, $stageChar->bestStreak(), 'stage(): накопили bestStreak == STAGE_HATCHLING_AT');
    assertSame(Stage::Hatchling, $stageChar->stage(), 'stage(): bestStreak == STAGE_HATCHLING_AT → Stage::Hatchling');

    // isStarvable внутри и за окном таймаута
    $starveCreatedAt = new \DateTimeImmutable('2026-05-01 00:00:00');
    $sc = Character::create('Starver', 'java', null, $starveCreatedAt);
    $withinWindow = $starveCreatedAt->modify('+' . (Constants::STARVE_TIMEOUT_HOURS - 1) . ' hours');
    $overWindow = $starveCreatedAt->modify('+' . (Constants::STARVE_TIMEOUT_HOURS + 1) . ' hours');

    assertTrue(!$sc->isStarvable($withinWindow), 'isStarvable(): внутри окна STARVE_TIMEOUT_HOURS → false');
    assertTrue($sc->isStarvable($overWindow), 'isStarvable(): за окном STARVE_TIMEOUT_HOURS → true');

    // recordUsage не сдвигает isStarvable (не входит в "последнее изменение")
    $sc->recordUsage();
    assertTrue($sc->isStarvable($overWindow), 'isStarvable(): recordUsage() не сдвигает "последнее изменение"');

    // после падения liveStreak в 0 стадия по bestStreak держится
    $sc->registerActiveDay('2026-05-01');
    $sc->registerActiveDay('2026-05-02');
    $sc->registerActiveDay('2026-05-03');
    assertSame(3, $sc->bestStreak(), 'подготовка: bestStreak == 3 перед проверкой протухания');
    assertSame(0, $sc->liveStreak('2026-05-10'), 'liveStreak(): протух после большого разрыва → 0');
    assertSame(Stage::fromBestStreak(3), $sc->stage(), 'stage(): после протухания liveStreak стадия по bestStreak держится');

    // ============================================================
    // P2.T4 — сериализация и фабрика: toArray/fromArray/create
    // ============================================================

    $fixedNow2 = new \DateTimeImmutable('2026-06-01 09:30:00');
    $orig = Character::create('Trinity', 'js', 'agent', $fixedNow2);
    $orig->feed(20, $fixedNow2->modify('+1 hour'));
    $orig->starve(5, $fixedNow2->modify('+2 hours'));
    $orig->recordUsage();
    $orig->registerActiveDay('2026-06-01');

    $data = $orig->toArray();
    $restored = Character::fromArray($data);

    assertSame($orig->id(), $restored->id(), 'round-trip: id сохранён');
    assertSame($orig->name(), $restored->name(), 'round-trip: name сохранён');
    assertSame($orig->skill(), $restored->skill(), 'round-trip: skill сохранён');
    assertSame($orig->satiety(), $restored->satiety(), 'round-trip: satiety сохранён');
    assertSame($orig->usageCount(), $restored->usageCount(), 'round-trip: usageCount сохранён');
    assertSame($orig->streak(), $restored->streak(), 'round-trip: streak сохранён');
    assertSame($orig->bestStreak(), $restored->bestStreak(), 'round-trip: bestStreak сохранён');
    assertSame($orig->stage(), $restored->stage(), 'round-trip: stage() (из bestStreak) совпадает');
    assertSame($orig->toArray(), $restored->toArray(), 'round-trip: toArray() идентичен после fromArray()');

    // fromArray записи без новых полей → безопасные дефолты
    $legacy = Character::fromArray([
        'id' => 'legacy-1',
        'name' => 'Legacy',
        'skill' => 'cobol',
        'createdAt' => $fixedNow2->format(DATE_ATOM),
    ]);
    assertSame(Constants::INITIAL_SATIETY, $legacy->satiety(), 'fromArray(): без satiety → дефолт INITIAL_SATIETY');
    assertSame(0, $legacy->usageCount(), 'fromArray(): без usageCount → дефолт 0');
    assertSame(0, $legacy->streak(), 'fromArray(): без streak → дефолт 0');
    assertSame(0, $legacy->bestStreak(), 'fromArray(): без bestStreak → дефолт 0');
    assertSame(0, $legacy->liveStreak('2026-06-01'), 'fromArray(): без lastUsedDate → liveStreak 0');
    assertSame(null, $legacy->toArray()['type'], 'fromArray(): без type → null');
    assertSame(null, $legacy->toArray()['lastFedAt'], 'fromArray(): без lastFedAt → null');
    assertSame(null, $legacy->toArray()['lastStarvedAt'], 'fromArray(): без lastStarvedAt → null');

    // create(..., $fixedNow) даёт satiety==INITIAL_SATIETY, нулевые счётчики, createdAt==$fixedNow
    $fresh = Character::create('Fresh', 'rust', null, $fixedNow2);
    assertSame(Constants::INITIAL_SATIETY, $fresh->satiety(), 'create(): satiety == INITIAL_SATIETY');
    assertSame(0, $fresh->usageCount(), 'create(): usageCount == 0');
    assertSame(0, $fresh->streak(), 'create(): streak == 0');
    assertSame(0, $fresh->bestStreak(), 'create(): bestStreak == 0');
    assertSame($fixedNow2->format(DATE_ATOM), $fresh->toArray()['createdAt'], 'create(): createdAt == $fixedNow (через toArray)');

    // уникальность id между экземплярами
    assertTrue($orig->id() !== $fresh->id(), 'create(): id уникален между экземплярами');

    // ============================================================
    // ФАЗА 3: P3.T1 — CharacterRepository (JSON-хранилище, mutate, уникальность skill)
    // ============================================================

    $repoDir = $tempTamagotchiHome . DIRECTORY_SEPARATOR . 'repo-test';
    assertTrue(!is_dir($repoDir), 'P3.T1 подготовка: repoDir ещё не существует');

    $repo2 = new CharacterRepository($repoDir);

    assertSame([], $repo2->all(), 'P3.T1: all() на отсутствующем файле → []');
    assertTrue(!is_dir($repoDir), 'P3.T1: чтение НЕ создаёт каталог dataDir');

    $pNow = new \DateTimeImmutable('2026-07-01 10:00:00');
    $p1 = Character::create('Agent Smith', 'python', null, $pNow);
    $repo2->save($p1);

    assertTrue(is_dir($repoDir), 'P3.T1: save() лениво создаёт каталог dataDir');
    assertTrue(is_file($repoDir . DIRECTORY_SEPARATOR . 'characters.json'), 'P3.T1: save() создаёт characters.json');

    $found = $repo2->findById($p1->id());
    assertTrue($found !== null, 'P3.T1: findById() находит сохранённого персонажа');
    assertSame($p1->toArray(), $found?->toArray(), 'P3.T1: save()+findById() round-trip идентичен toArray()');

    $foundBySkill = $repo2->findBySkill('python');
    assertTrue($foundBySkill !== null, 'P3.T1: findBySkill() находит сохранённого персонажа');
    assertSame($p1->id(), $foundBySkill?->id(), 'P3.T1: findBySkill() возвращает нужного персонажа');

    // Дубль skill → исключение
    $p2 = Character::create('Agent Brown', 'python', null, $pNow);
    $duplicateThrew = false;
    try {
        $repo2->save($p2);
    } catch (\RuntimeException $e) {
        $duplicateThrew = true;
    }
    assertTrue($duplicateThrew, 'P3.T1: save() второго персонажа с тем же skill бросает RuntimeException');
    assertSame(1, count($repo2->all()), 'P3.T1: после неудачного save() дубля данные не испорчены (всё ещё 1 запись)');

    // save() с другим skill проходит нормально
    $p3 = Character::create('Agent Jones', 'golang', null, $pNow);
    $repo2->save($p3);
    assertSame(2, count($repo2->all()), 'P3.T1: save() с уникальным skill добавляет запись');

    // delete() удаляет
    $repo2->delete($p3->id());
    assertSame(null, $repo2->findById($p3->id()), 'P3.T1: delete() удаляет запись (findById → null)');
    assertSame(1, count($repo2->all()), 'P3.T1: delete() уменьшает количество записей');

    // mutate(): последовательные RMW-колбэки не теряют апдейты
    $repo2->mutate(static function (array $records): array {
        foreach ($records as &$record) {
            $record['satiety'] = 77;
        }

        return $records;
    });
    $repo2->mutate(static function (array $records): array {
        foreach ($records as &$record) {
            $record['usageCount'] = 42;
        }

        return $records;
    });
    $afterMutate = $repo2->findById($p1->id());
    assertSame(77, $afterMutate?->satiety(), 'P3.T1: mutate(): первый колбэк применён (satiety)');
    assertSame(42, $afterMutate?->usageCount(), 'P3.T1: mutate(): второй колбэк применён поверх первого (usageCount)');

    // ============================================================
    // ФАЗА 3: P3.T2 — SessionStore (sessions.json, TTL-чистка)
    // ============================================================

    $sessionDir = $tempTamagotchiHome . DIRECTORY_SEPARATOR . 'session-test';
    $sessionStore2 = new SessionStore($sessionDir);

    assertSame([], $sessionStore2->getUsedSkills('unknown-session'), 'P3.T2: getUsedSkills() неизвестной сессии → []');

    $sessionNow = new \DateTimeImmutable('2026-07-01 09:00:00');
    $sessionStore2->markSkillUsed('sess-1', 'php', $sessionNow);
    $sessionStore2->markSkillUsed('sess-1', 'php', $sessionNow->modify('+1 minute'));

    $usedSkills = $sessionStore2->getUsedSkills('sess-1');
    assertSame(1, count($usedSkills), 'P3.T2: markSkillUsed() дважды одним skill → в наборе один раз');
    assertSame('php', $usedSkills[0], 'P3.T2: getUsedSkills() возвращает отмеченный skill');

    $sessionStore2->markSkillUsed('sess-1', 'js', $sessionNow->modify('+2 minutes'));
    $usedSkillsAfterSecond = $sessionStore2->getUsedSkills('sess-1');
    assertSame(2, count($usedSkillsAfterSecond), 'P3.T2: markSkillUsed() с новым skill добавляет его в набор');
    assertTrue(in_array('php', $usedSkillsAfterSecond, true) && in_array('js', $usedSkillsAfterSecond, true), 'P3.T2: набор содержит оба отмеченных skill');

    // clear() удаляет сессию
    $sessionStore2->clear('sess-1');
    assertSame([], $sessionStore2->getUsedSkills('sess-1'), 'P3.T2: clear() удаляет сессию (getUsedSkills → [])');

    // pruneExpired(): удаляет протухшие, оставляет свежие
    $baseTime = new \DateTimeImmutable('2026-07-10 00:00:00');
    $sessionStore2->markSkillUsed('sess-old', 'ruby', $baseTime);
    $sessionStore2->markSkillUsed('sess-fresh', 'rust', $baseTime->modify('+' . (Constants::SESSION_TTL_HOURS - 1) . ' hours'));

    $pruneNow = $baseTime->modify('+' . (Constants::SESSION_TTL_HOURS + 1) . ' hours');
    $sessionStore2->pruneExpired(Constants::SESSION_TTL_HOURS, $pruneNow);

    assertSame([], $sessionStore2->getUsedSkills('sess-old'), 'P3.T2: pruneExpired() удаляет сессию старше SESSION_TTL_HOURS');
    assertSame(['rust'], $sessionStore2->getUsedSkills('sess-fresh'), 'P3.T2: pruneExpired() оставляет свежую сессию нетронутой');

    // pruneExpired() идемпотентен
    $sessionStore2->pruneExpired(Constants::SESSION_TTL_HOURS, $pruneNow);
    assertSame(['rust'], $sessionStore2->getUsedSkills('sess-fresh'), 'P3.T2: повторный pruneExpired() идемпотентен, свежая сессия на месте');

    // DTO-02: usedSkills в сыром sessions.json на диске — JSON-массив, а не объект
    $rawSessionsPath = $sessionDir . DIRECTORY_SEPARATOR . 'sessions.json';
    $sessionStore2->markSkillUsed('sess-dto02', 'php', $pruneNow);
    $sessionStore2->markSkillUsed('sess-dto02', 'js', $pruneNow->modify('+1 minute'));

    $rawSessionsData = json_decode(file_get_contents($rawSessionsPath), true);
    assertTrue(
        array_is_list($rawSessionsData['sess-dto02']['usedSkills']),
        'DTO-02: usedSkills в sessions.json на диске сериализован как JSON-массив (array_is_list)'
    );
} finally {
    // --- Очистка временного каталога ---
    recursiveRemove($tempRoot);
}

echo "\n{$total} assertions, " . ($total - $failures) . " passed, {$failures} failed.\n";

exit($failures > 0 ? 1 : 0);
