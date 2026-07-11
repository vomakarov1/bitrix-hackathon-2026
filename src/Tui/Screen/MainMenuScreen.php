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

/**
 * Главное меню: список питомцев / создать / удалить / выход.
 */
final class MainMenuScreen implements ScreenInterface
{
    private readonly AbstractWidget $root;
    private readonly SelectListWidget $select;

    /**
     * @param \Closure(string): void $onNavigate Вызывается с value выбранного пункта.
     * @param \Closure(): void       $onExit     Вызывается при отмене (Esc/Ctrl+C).
     */
    public function __construct(
        private readonly \Closure $onNavigate,
        private readonly \Closure $onExit,
    ) {
        $this->select = new SelectListWidget([
            ['value' => 'list', 'label' => 'Мои питомцы'],
            ['value' => 'create', 'label' => 'Создать питомца'],
            ['value' => 'delete', 'label' => 'Удалить питомца'],
            ['value' => 'quit', 'label' => 'Выход'],
        ]);

        $this->select->onSelect(function (SelectEvent $event): void {
            ($this->onNavigate)($event->getValue());
        });

        $this->select->onCancel(function (): void {
            ($this->onExit)();
        });

        $title = new TextWidget('🐾 Тамагочи — главное меню');
        $title->setStyle((new Style())->withBold()->withColor('cyan'));

        $container = new ContainerWidget();
        $container->setStyle((new Style())->withDirection(Direction::Vertical)->withGap(1));
        $container->add($title);
        $container->add($this->select);

        $this->root = $container;
    }

    public function rootWidget(): AbstractWidget
    {
        return $this->root;
    }

    public function focusTarget(): ?AbstractWidget
    {
        return $this->select;
    }
}
