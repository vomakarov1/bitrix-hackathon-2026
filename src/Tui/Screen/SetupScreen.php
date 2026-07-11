<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Tui\Screen;

use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Экран установки интеграции с кодовым агентом: по входу вызывает установку
 * хуков (идемпотентно — HooksInstaller) и показывает результат + «Назад».
 *
 * Установка запускается в конструкторе: это ровно тот момент, когда пользователь
 * выбрал пункт «Установить хуки» в меню. Операция идемпотентна, поэтому побочный
 * эффект при входе на экран безопасен.
 */
final class SetupScreen implements ScreenInterface
{
    private readonly AbstractWidget $root;
    private readonly SelectListWidget $select;

    /**
     * @param \Closure(): string $runSetup Выполняет установку хуков, возвращает человекочитаемый результат.
     * @param \Closure(): void   $onBack   Возврат в главное меню (выбор «Назад» или Esc).
     */
    public function __construct(\Closure $runSetup, \Closure $onBack)
    {
        $result = ($runSetup)();

        $title = new TextWidget('🔌 Интеграция с кодовым агентом');
        $title->setStyle((new Style())->withBold()->withColor('cyan'));

        $this->select = new SelectListWidget([
            ['value' => 'back', 'label' => 'Назад в меню'],
        ]);
        $this->select->onSelect(static function () use ($onBack): void {
            ($onBack)();
        });
        $this->select->onCancel(static function () use ($onBack): void {
            ($onBack)();
        });

        $container = new ContainerWidget();
        $container->setStyle((new Style())->withDirection(Direction::Vertical)->withGap(1));
        $container->add($title);
        // Результат может быть многострочным — каждую строку отдельным виджетом.
        foreach (explode("\n", $result) as $line) {
            $container->add(new TextWidget($line));
        }
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
