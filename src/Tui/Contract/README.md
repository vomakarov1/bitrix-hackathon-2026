# ⚠️ ВРЕМЕННЫЕ ЗАГЛУШКИ

Интерфейсы `Contract/*` и фейки `Contract/Fake/*` в этой директории —
строительные леса на время отсутствия блоков A (домен персонажа) и B
(CLI-роутер/скиллы) (см. SDD §1.1). Они существуют только для того, чтобы
презентационный слой TUI (`src/Tui/`, блок C) мог быть реализован и
протестирован автономно, без готовых реальных реализаций.

## Обязательство на момент интеграции с блоками A/B

После реализации реальных контрактов:

1. Реальные `Character`/`Stage`/`CharacterRepository`/`CharacterService`/
   `SkillCatalog`/`Clock` (блоки A/B) должны либо удовлетворять интерфейсам
   `Contract/*`, либо эти интерфейсы должны быть заменены реальными типами
   домена.
2. `Contract/Fake/*` — удалить целиком, без остатка.
3. Точку сборки `MenuApp` (сейчас собирается вручную на фейках в тестах и
   демонстрационных скриптах) перецепить на реальные объекты — через будущий
   роутер `bin/tamagotchi` (блок B).

Пока `Contract/Fake/*` присутствуют в дереве — интеграция с блоками A/B
**не закрыта**, а TUI работает исключительно в автономном/демонстрационном
режиме поверх фейковых данных.

## Файлы-заглушки

### `Contract/*` (интерфейсы, временные до появления реального домена)

- `CharacterRepositoryInterface.php`
- `CharacterServiceInterface.php`
- `CharacterView.php`
- `ClockInterface.php`
- `SkillCatalogInterface.php`
- `StageView.php`

### `Contract/Fake/*` (реализации-заглушки, удалить целиком на интеграции)

- `FakeCharacter.php`
- `FakeStage.php`
- `FixedClock.php`
- `InMemoryCharacterRepository.php`
- `InMemoryCharacterService.php`
- `StaticSkillCatalog.php`

Каждый из перечисленных файлов уже помечен тегом `@todo TEMPORARY` в
своём PHPDoc — это единообразный маркер для поиска (`grep -r "@todo TEMPORARY" src/Tui/Contract`)
всех мест, подлежащих замене/удалению.
