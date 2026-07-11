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
     * @param \Closure(string): void $onNavigate    Вызывается с value выбранного пункта.
     * @param \Closure(): void       $onExit        Вызывается при отмене (Esc/Ctrl+C).
     * @param bool                   $setupAvailable Показывать ли пункт установки хуков кодового агента.
     */
    public function __construct(
        private readonly \Closure $onNavigate,
        private readonly \Closure $onExit,
        bool $setupAvailable = false,
    ) {
        $items = [
            ['value' => 'list', 'label' => 'Мои питомцы'],
            ['value' => 'create', 'label' => 'Создать питомца'],
            ['value' => 'delete', 'label' => 'Удалить питомца'],
        ];
        if ($setupAvailable) {
            $items[] = ['value' => 'setup', 'label' => 'Установить хуки для кодового агента'];
        }
        $items[] = ['value' => 'quit', 'label' => 'Выход'];

        $this->select = new SelectListWidget($items);

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
