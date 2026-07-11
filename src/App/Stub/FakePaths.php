<?php

declare(strict_types=1);

namespace Vladislavmakarov\BitrixHackathon2026\App\Stub;

use Vladislavmakarov\BitrixHackathon2026\App\Contracts\PathsInterface;

/**
 * Минимальная in-memory заглушка PathsInterface (§2.3 SDD-B) — только чтобы B
 * собрался и smoke-кейсы прошли до готовности A. В прод не поставляется.
 *
 * Принимает базовые пути в конструкторе (для env-override `TAMAGOTCHI_HOME` /
 * `CLAUDE_CONFIG_DIR` в тестах); по умолчанию — временные каталоги, чтобы не
 * трогать реальный конфиг Claude.
 */
final class FakePaths implements PathsInterface
{
    private readonly string $claudeConfigDir;
    private readonly string $dataDir;
    private readonly string $settingsPath;

    public function __construct(?string $claudeConfigDir = null, ?string $dataDir = null, ?string $settingsPath = null)
    {
        $envClaudeConfigDir = getenv('CLAUDE_CONFIG_DIR');
        $envHome = getenv('TAMAGOTCHI_HOME');

        $this->claudeConfigDir = $claudeConfigDir
            ?? (($envClaudeConfigDir !== false && $envClaudeConfigDir !== '')
                ? $envClaudeConfigDir
                : sys_get_temp_dir().'/tamagotchi-claude');

        $base = ($envHome !== false && $envHome !== '') ? rtrim($envHome, '/') : sys_get_temp_dir().'/tamagotchi-home';

        $this->dataDir = $dataDir ?? $base.'/data';
        $this->settingsPath = $settingsPath ?? $this->claudeConfigDir.'/settings.json';
    }

    public function claudeConfigDir(): string
    {
        return $this->claudeConfigDir;
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    public function settingsPath(): string
    {
        return $this->settingsPath;
    }
}
