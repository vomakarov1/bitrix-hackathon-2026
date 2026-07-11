<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Screen;

use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterServiceInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\SkillCatalogInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\TypeCatalog;

/**
 * Пошаговый мастер создания питомца: скилл → имя → тип → CharacterService::create().
 *
 * Реализован как внутренний FSM: каждый шаг строит своё поддерево виджетов
 * и сохраняет его в $root/$focus. Экран не имеет доступа к Tui напрямую —
 * поэтому после каждого перехода между шагами он вызывает переданный
 * requestRefresh(), которым MenuApp повторно "показывает" этот же экран
 * (заново читает rootWidget()/focusTarget() и переустанавливает фокус).
 *
 * Навигация Esc внутри мастера идёт на шаг назад (type→name→skill), а Esc
 * на первом шаге (или на экране "нет свободных скиллов") — это отмена всего
 * мастера через колбэк onCancel.
 */
final class CreatePetScreen implements ScreenInterface
{
    private AbstractWidget $root;
    private ?AbstractWidget $focus;

    private ?string $selectedSkill = null;
    private string $name = '';

    /**
     * MenuApp конструирует этот экран через `new CreatePetScreen(...)` и лишь
     * ПОСЛЕ этого присваивает результат в переменную, на которую замыкается
     * $requestRefresh (по ссылке — объект ещё не существует, пока конструктор
     * не завершился). Поэтому первый шаг, построенный из конструктора, не
     * должен дёргать requestRefresh(); MenuApp сама показывает экран сразу
     * после `new`. Этот флаг отличает "первичная сборка" от последующих
     * переходов между шагами (которые уже вызываются из обработчиков событий,
     * когда объект полностью инициализирован).
     */
    private bool $constructed = false;

    private const TITLE = '🐾 Тамагочи — создать питомца';

    /**
     * @param \Closure(): void $onDone         Вызывается после успешного создания.
     * @param \Closure(): void $onCancel       Вызывается при отмене всего мастера.
     * @param \Closure(): void $requestRefresh Вызывается после смены шага, чтобы MenuApp пере-показал экран.
     */
    public function __construct(
        private readonly SkillCatalogInterface $skillCatalog,
        private readonly CharacterRepositoryInterface $repository,
        private readonly CharacterServiceInterface $characterService,
        private readonly TypeCatalog $typeCatalog,
        private readonly \Closure $onDone,
        private readonly \Closure $onCancel,
        private readonly \Closure $requestRefresh,
    ) {
        $this->buildSkillStep();
        $this->constructed = true;
    }

    public function rootWidget(): AbstractWidget
    {
        return $this->root;
    }

    public function focusTarget(): ?AbstractWidget
    {
        return $this->focus;
    }

    private function title(): TextWidget
    {
        $title = new TextWidget(self::TITLE);
        $title->setStyle((new Style())->withBold()->withColor('cyan'));

        return $title;
    }

    private function newStepContainer(): ContainerWidget
    {
        $container = new ContainerWidget();
        $container->setStyle((new Style())->withDirection(Direction::Vertical)->withGap(1));
        $container->add($this->title());

        return $container;
    }

    /** @return string[] Скиллы каталога минус уже занятые существующими питомцами. */
    private function availableSkills(): array
    {
        $taken = array_map(
            static fn (CharacterView $character): string => $character->skill(),
            $this->repository->all(),
        );

        return array_values(array_diff($this->skillCatalog->all(), $taken));
    }

    private function buildSkillStep(?string $errorMessage = null): void
    {
        $available = $this->availableSkills();

        if ([] === $available) {
            $this->buildNoSkillsStep();

            return;
        }

        $container = $this->newStepContainer();

        if (null !== $errorMessage) {
            $error = new TextWidget(StringUtils::stripControlBytes($errorMessage));
            $error->setStyle((new Style())->withBold()->withColor('yellow'));
            $container->add($error);
        }

        $container->add(new TextWidget('Выберите скилл для нового питомца:'));

        $items = array_map(
            static fn (string $skill): array => ['value' => $skill, 'label' => StringUtils::stripControlBytes($skill)],
            $available,
        );

        $select = new SelectListWidget($items);
        $select->onSelect(function (SelectEvent $event): void {
            $this->selectedSkill = $event->getValue();
            $this->buildNameStep();
        });
        $select->onCancel(function (): void {
            ($this->onCancel)();
        });

        $container->add($select);

        $this->root = $container;
        $this->focus = $select;

        $this->refresh();
    }

    private function buildNoSkillsStep(): void
    {
        $container = $this->newStepContainer();
        $container->add(new TextWidget('Все скиллы уже заняты — создавать нечего.'));

        $select = new SelectListWidget([['value' => 'ok', 'label' => 'Понятно']]);
        $select->onSelect(function (): void {
            ($this->onCancel)();
        });
        $select->onCancel(function (): void {
            ($this->onCancel)();
        });

        $container->add($select);

        $this->root = $container;
        $this->focus = $select;

        $this->refresh();
    }

    private function buildNameStep(?string $errorMessage = null): void
    {
        $container = $this->newStepContainer();

        if (null !== $errorMessage) {
            $error = new TextWidget(StringUtils::stripControlBytes($errorMessage));
            $error->setStyle((new Style())->withBold()->withColor('yellow'));
            $container->add($error);
        }

        $container->add(new TextWidget('Введите имя питомца:'));

        $input = new InputWidget();
        $input->setPrompt('> ');
        $input->onSubmit(function (SubmitEvent $event): void {
            if ($event->isBlank()) {
                $this->buildNameStep('Имя не может быть пустым.');

                return;
            }

            $this->name = trim($event->getValue());
            $this->buildTypeStep();
        });
        $input->onCancel(function (): void {
            $this->buildSkillStep();
        });

        $container->add($input);

        $this->root = $container;
        $this->focus = $input;

        $this->refresh();
    }

    private function buildTypeStep(): void
    {
        $container = $this->newStepContainer();
        $container->add(new TextWidget('Выберите тип питомца:'));

        $items = array_map(
            static fn (string $type): array => ['value' => $type, 'label' => StringUtils::stripControlBytes($type)],
            $this->typeCatalog->all(),
        );

        $select = new SelectListWidget($items);
        $select->onSelect(function (SelectEvent $event): void {
            $this->createCharacter($event->getValue());
        });
        $select->onCancel(function (): void {
            $this->buildNameStep();
        });

        $container->add($select);

        $this->root = $container;
        $this->focus = $select;

        $this->refresh();
    }

    private function createCharacter(string $type): void
    {
        \assert(null !== $this->selectedSkill);

        try {
            $this->characterService->create($this->name, $this->selectedSkill, $type);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->buildSkillStep($exception->getMessage());

            return;
        }

        ($this->onDone)();
    }

    private function refresh(): void
    {
        if (!$this->constructed) {
            // Первичная сборка внутри конструктора: MenuApp ещё не успела
            // сохранить $this в переменную для requestRefresh (см. свойство
            // $constructed) — она покажет экран сама сразу после `new`.
            return;
        }

        ($this->requestRefresh)();
    }
}
