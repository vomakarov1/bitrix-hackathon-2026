<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\Storage;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\PathsInterface;

/**
 * Резолв путей конфигурации и данных с env-override (К4).
 *
 * Paths ТОЛЬКО вычисляет пути и ничего не создаёт на диске (без side effects).
 * Существование каталогов обеспечивают потребители (см. P3/setup).
 */
final class Paths implements PathsInterface
{
    /**
     * Каталог конфигурации Claude: CLAUDE_CONFIG_DIR | ~/.claude.
     */
    public function claudeConfigDir(): string
    {
        $override = getenv('CLAUDE_CONFIG_DIR');
        if ($override !== false && $override !== '') {
            return $this->normalize($override);
        }

        return $this->normalize($this->homeDir() . '/.claude');
    }

    /**
     * Каталог данных приложения: TAMAGOTCHI_HOME | claudeConfigDir/tamagotchi/.
     */
    public function dataDir(): string
    {
        $override = getenv('TAMAGOTCHI_HOME');
        if ($override !== false && $override !== '') {
            return $this->normalize($override);
        }

        return $this->normalize($this->claudeConfigDir() . '/tamagotchi');
    }

    /**
     * Путь к settings.json: claudeConfigDir/settings.json.
     */
    public function settingsPath(): string
    {
        return $this->normalize($this->claudeConfigDir() . '/settings.json');
    }

    /**
     * Домашний каталог пользователя — кроссплатформенно (Linux/macOS: HOME, Windows: USERPROFILE).
     */
    private function homeDir(): string
    {
        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            return $home;
        }

        $userProfile = getenv('USERPROFILE');
        if ($userProfile !== false && $userProfile !== '') {
            return $userProfile;
        }

        throw new \RuntimeException('Cannot resolve home directory: neither HOME nor USERPROFILE is set.');
    }

    /**
     * Нормализует путь: приводит слэши к DIRECTORY_SEPARATOR и убирает дублирующиеся разделители.
     */
    private function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $separator = preg_quote(DIRECTORY_SEPARATOR, '#');

        // Не схлопываем ведущие двойные разделители (UNC-пути \\server\share).
        $prefix = '';
        if (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        }

        $path = (string) preg_replace('#' . $separator . '{2,}#', DIRECTORY_SEPARATOR, $path);

        return $prefix . rtrim($path, DIRECTORY_SEPARATOR);
    }
}
