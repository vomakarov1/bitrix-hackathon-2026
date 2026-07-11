<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Integration;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\PathsInterface;

/**
 * Идемпотентный мёрдж хуков в `settings.json` (Р6 ADR, §4.7 SDD-B). Трогает
 * файл только по явной команде `setup`.
 */
final class HooksInstaller
{
    public function __construct(private readonly PathsInterface $paths)
    {
    }

    /** @param string $binPath абсолютный путь к bin/tamagotchi */
    public function install(string $binPath): void
    {
        $cmdTool = PHP_BINARY.' '.escapeshellarg($binPath).' hook:tool-use';
        $cmdEnd = PHP_BINARY.' '.escapeshellarg($binPath).' hook:session-end';

        $settingsPath = $this->paths->settingsPath();
        $settings = $this->readSettings($settingsPath);

        // matcher PostToolUse == "Skill": сверено с живым payload (2026-07-11) —
        // при вызове скилла tool_name == "Skill", имя скилла в tool_input.skill.
        // Так хук срабатывает ТОЛЬКО на использование скиллов, а не на каждый
        // инструмент (Bash/Read/…), — без лишнего оверхеда.
        $this->ensureHookEntry($settings, 'PostToolUse', 'Skill', $cmdTool, $binPath, 'hook:tool-use');
        $this->ensureHookEntry($settings, 'SessionEnd', '', $cmdEnd, $binPath, 'hook:session-end');

        $this->writeAtomic($settingsPath, $settings);

        $dataDir = $this->paths->dataDir();
        if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог данных "%s".', $dataDir));
        }
    }

    /**
     * Нет файла -> {}. Битый JSON -> исключение, файл НЕ трогаем (§9.5 SDD-B).
     *
     * @return array<string,mixed>
     */
    private function readSettings(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = trim((string) file_get_contents($path));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf(
                'Файл "%s" повреждён (невалидный JSON) — settings.json не изменён.',
                $path,
            ));
        }

        return $decoded;
    }

    /**
     * Добавляет элемент `hooks.$event[]`, только если наша команда (по подстроке
     * `$binPath` + `$suffix`) ещё не присутствует — идемпотентность (§4.7 SDD-B).
     *
     * @param array<string,mixed> $settings
     */
    private function ensureHookEntry(array &$settings, string $event, string $matcher, string $command, string $binPath, string $suffix): void
    {
        $entries = $settings['hooks'][$event] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }

        foreach ($entries as $index => $entry) {
            $hooks = is_array($entry) ? ($entry['hooks'] ?? []) : [];
            foreach ((array) $hooks as $hook) {
                $existing = is_array($hook) ? (string) ($hook['command'] ?? '') : '';
                if (str_contains($existing, $binPath) && str_contains($existing, $suffix)) {
                    // Наша запись уже есть — актуализируем matcher (миграция
                    // старого "Skill" -> "" при повторном setup), команду не трогаем.
                    $entries[$index]['matcher'] = $matcher;
                    $settings['hooks'][$event] = $entries;

                    return;
                }
            }
        }

        $entries[] = [
            'matcher' => $matcher,
            'hooks' => [
                ['type' => 'command', 'command' => $command],
            ],
        ];

        $settings['hooks'][$event] = $entries;
    }

    /** Атомарная запись: temp-файл + rename, pretty JSON, исходные ключи сохранены. */
    private function writeAtomic(string $path, array $settings): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог "%s" для settings.json.', $dir));
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Не удалось сериализовать settings.json.');
        }

        $tmpPath = $path.'.tmp.'.bin2hex(random_bytes(4));
        if (file_put_contents($tmpPath, $json."\n") === false) {
            throw new \RuntimeException(sprintf('Не удалось записать временный файл "%s".', $tmpPath));
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf('Не удалось атомарно заменить файл "%s".', $path));
        }
    }
}
