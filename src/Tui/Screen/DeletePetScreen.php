<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Screen;

use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterServiceInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\PetPresenter;

/**
 * Удаление питомца в два шага: выбор питомца → подтверждение (да/нет).
 *
 * Как и CreatePetScreen, это внутренний FSM: каждый шаг перестраивает
 * $root/$focus и (кроме первичной сборки в конструкторе — см. $constructed)
 * дёргает requestRefresh(), чтобы MenuApp пере-показала актуальный виджет
 * и переустановила фокус.
 *
 * Отступление от буквального текста задачи 2.4: там requestRefresh не
 * упомянут явно, но без него смена шага (выбор → подтверждение) не будет
 * видна пользователю — тот же механизм, что и в CreatePetScreen (см. §2.5
 * "Для перефокусировки при смене шага внутри мастера"), применён и здесь.
 */
final class DeletePetScreen implements ScreenInterface
{
    private AbstractWidget $root;
    private ?AbstractWidget $focus;

    private bool $constructed = false;

    private const TITLE = '🐾 Тамагочи — удалить питомца';

    /**
     * @param \Closure(): void $onDone         Вызывается после успешного удаления.
     * @param \Closure(): void $onCancel       Вызывается при отмене (пусто/Esc/«Отмена»).
     * @param \Closure(): void $requestRefresh Вызывается после смены шага, чтобы MenuApp пере-показал экран.
     */
    public function __construct(
        private readonly CharacterRepositoryInterface $repository,
        private readonly CharacterServiceInterface $characterService,
        private readonly PetPresenter $presenter,
        private readonly ClockInterface $clock,
        private readonly \Closure $onDone,
        private readonly \Closure $onCancel,
        private readonly \Closure $requestRefresh,
    ) {
        $this->buildSelectStep();
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

    private function buildSelectStep(): void
    {
        $characters = $this->repository->all();

        if ([] === $characters) {
            $select = new SelectListWidget([['value' => 'ok', 'label' => 'Понятно (питомцев нет)']]);
            $select->onSelect(function (): void {
                ($this->onCancel)();
            });
            $select->onCancel(function (): void {
                ($this->onCancel)();
            });

            $container = $this->newStepContainer();
            $container->add($select);

            $this->root = $container;
            $this->focus = $select;

            $this->refresh();

            return;
        }

        $today = $this->clock->today();

        $items = array_map(
            fn (CharacterView $character): array => [
                'value' => $character->id(),
                'label' => StringUtils::stripControlBytes(sprintf('%s (%s)', $character->name(), $character->skill())),
                'description' => $this->presenter->mood($character),
            ],
            $characters,
        );

        $select = new SelectListWidget($items);
        $select->onSelect(function (SelectEvent $event): void {
            $this->buildConfirmStep($event->getValue(), $event->getLabel());
        });
        $select->onCancel(function (): void {
            ($this->onCancel)();
        });

        $container = $this->newStepContainer();
        $container->add($select);

        $this->root = $container;
        $this->focus = $select;

        $this->refresh();
    }

    private function buildConfirmStep(string $id, string $label): void
    {
        $select = new SelectListWidget([
            ['value' => 'yes', 'label' => sprintf('Да, удалить «%s»', $label)],
            ['value' => 'no', 'label' => 'Отмена'],
        ]);

        $select->onSelect(function (SelectEvent $event) use ($id): void {
            if ('yes' === $event->getValue()) {
                $this->characterService->delete($id);
                ($this->onDone)();

                return;
            }

            $this->buildSelectStep();
        });
        $select->onCancel(function (): void {
            $this->buildSelectStep();
        });

        $warning = new TextWidget('⚠ Подтвердите удаление:');
        $warning->setStyle((new Style())->withBold()->withColor('yellow'));

        $container = $this->newStepContainer();
        $container->add($warning);
        $container->add($select);

        $this->root = $container;
        $this->focus = $select;

        $this->refresh();
    }

    private function refresh(): void
    {
        if (!$this->constructed) {
            return;
        }

        ($this->requestRefresh)();
    }
}
