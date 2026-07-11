<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Screen;

use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterRepositoryInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\CharacterView;
use Vladislavmakarov\BitrixHackathon2026\Tui\Contract\ClockInterface;
use Vladislavmakarov\BitrixHackathon2026\Tui\PetPresenter;

/**
 * Список питомцев карточками. Пустой список сам себя не перенаправляет —
 * решение о том, куда вести дальше (напр. на создание), принимает MenuApp
 * по признаку isEmpty().
 */
final class PetListScreen implements ScreenInterface
{
    private readonly AbstractWidget $root;
    private readonly ?AbstractWidget $focus;
    private readonly bool $empty;

    /** @param \Closure(): void $onBack Вызывается при Enter/Esc на списке. */
    public function __construct(
        CharacterRepositoryInterface $repository,
        PetPresenter $presenter,
        ClockInterface $clock,
        private readonly \Closure $onBack,
    ) {
        $characters = $repository->all();
        $this->empty = [] === $characters;

        $title = new TextWidget('🐾 Тамагочи — мои питомцы');
        $title->setStyle((new Style())->withBold()->withColor('cyan'));

        if ($this->empty) {
            $container = new ContainerWidget();
            $container->setStyle((new Style())->withDirection(Direction::Vertical)->withGap(1));
            $container->add($title);
            $container->add(new TextWidget('Питомцев пока нет.'));

            $this->root = $container;
            $this->focus = null;

            return;
        }

        $today = $clock->today();

        $container = new ContainerWidget();
        $container->setStyle((new Style())->withDirection(Direction::Vertical)->withGap(1));
        $container->add($title);

        foreach ($characters as $character) {
            $container->add(new TextWidget($presenter->card($character, $today)));
        }

        $container->add(new TextWidget('Enter/Esc — назад'));

        $items = array_map(
            static fn (CharacterView $character): array => [
                'value' => $character->id(),
                'label' => StringUtils::stripControlBytes($character->name()),
            ],
            $characters,
        );

        $select = new SelectListWidget($items);
        $select->onSelect(function (): void {
            ($this->onBack)();
        });
        $select->onCancel(function (): void {
            ($this->onBack)();
        });

        $container->add($select);

        $this->root = $container;
        $this->focus = $select;
    }

    /** Список пуст — MenuApp решает, куда навигировать вместо показа этого экрана. */
    public function isEmpty(): bool
    {
        return $this->empty;
    }

    public function rootWidget(): AbstractWidget
    {
        return $this->root;
    }

    public function focusTarget(): ?AbstractWidget
    {
        return $this->focus;
    }
}
