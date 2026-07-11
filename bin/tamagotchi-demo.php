<?php
// Демо-запуск TUI-слоя на временных фейках (блоки A/B ещё не реализованы).
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vladislavmakarov\BitrixHackathon2026\Tui\MenuApp;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\FakeCharacter;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\FakeStage;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\FixedClock;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\InMemoryCharacterRepository;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\InMemoryCharacterService;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\Fake\StaticSkillCatalog;

$stage = new FakeStage('Зверь', '🦊', 'fox');
$pet = new FakeCharacter(
    id: 'p1', name: 'Барсик', skill: 'develop', type: 'fox',
    satiety: 82, usageCount: 12, mood: 'сыт', level: 3,
    streak: 5, bestStreak: 5, liveStreakValue: 5, stage: $stage,
);

$repo = new InMemoryCharacterRepository([$pet]);
$skills = new StaticSkillCatalog();
$service = new InMemoryCharacterService($repo, $skills);
$clock = new FixedClock('2026-07-11');

$app = new MenuApp($repo, $service, $skills, $clock);
exit($app->run());
