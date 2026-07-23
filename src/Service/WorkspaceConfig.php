<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use HeartPhrame\Config\ConfigInterface;

use function array_replace_recursive;
use function in_array;
use function is_file;
use function is_scalar;
use function preg_replace;
use function rtrim;
use function strtolower;
use function trim;

final readonly class WorkspaceConfig
{
    /**
     * @var array<string, mixed>
     */
    private array $workspaceConfig;

    /**
     * HR: Spaja zadane postavke modula i aplikacijski `config/workspace.php`.
     * EN: Merges module defaults and the application `config/workspace.php`.
     */
    public function __construct(
        private ConfigInterface $config,
        private string $moduleRoot,
    ) {
        $this->workspaceConfig = $this->loadWorkspaceConfig();
    }

    /**
     * HR: Vraća korijenski URL segment unutar kojeg područja određuju vlastite slugove.
     * EN: Returns the root URL segment under which workspaces define their own slugs.
     */
    public function rootPath(): string
    {
        $routing = $this->section('routing');
        $path = is_scalar($routing['root_path'] ?? null) ? strtolower(trim((string)$routing['root_path'])) : '';
        $path = trim((string)preg_replace('/[^a-z0-9-]+/', '-', $path), '-');

        return $path !== '' ? $path : 'workspace';
    }

    /**
     * HR: Vraća zadanu vidljivost novog područja.
     * EN: Returns the default visibility for a new workspace.
     */
    public function defaultVisibility(): string
    {
        $defaults = $this->section('defaults');
        $visibility = is_scalar($defaults['visibility'] ?? null)
        ? strtolower(trim((string)$defaults['visibility']))
        : '';

        return in_array($visibility, ['restricted', 'authenticated', 'public'], true)
        ? $visibility
        : 'restricted';
    }

    /**
     * HR: Određuje je li stablo stranica početno otvoreno.
     * EN: Determines whether the page tree is initially expanded.
     */
    public function treeVisibleByDefault(): bool
    {
        $defaults = $this->section('defaults');

        return (bool)($defaults['tree_visible'] ?? true);
    }

    /**
     * HR: Određuje smiju li svi prijavljeni korisnici kreirati nova područja.
     * EN: Determines whether every authenticated user may create new workspaces.
     */
    public function authenticatedUsersMayCreate(): bool
    {
        $creation = $this->section('creation');

        return (bool)($creation['authenticated_users'] ?? false);
    }

    /**
     * HR: Vraća treba li modul automatski dodati glavnu menu stavku.
     * EN: Returns whether the module should automatically add its main menu item.
     */
    public function shouldAutoRegisterTopMenu(): bool
    {
        $menu = $this->section('menu');

        return (bool)($menu['auto_register_top'] ?? true);
    }

    /**
     * HR: Vraća treba li modul automatski dodati administratorske postavke.
     * EN: Returns whether the module should automatically add administration settings.
     */
    public function shouldAutoRegisterSettingsMenu(): bool
    {
        $menu = $this->section('menu');

        return (bool)($menu['auto_register_settings'] ?? true);
    }

    /**
     * HR: Provjerava je li drugi modul uključen u host aplikaciji.
     * EN: Checks whether another module is enabled in the host application.
     */
    public function isAppModuleEnabled(string $packageName): bool
    {
        $enabledModules = $this->config->getAsArrayWithValuesAsNonEmptyStrings('app.modules.enabled') ?? [];

        return in_array($packageName, $enabledModules, true);
    }

    /**
     * HR: Vraća apsolutnu putanju aplikacijske datoteke postavki.
     * EN: Returns the absolute path of the application settings file.
     */
    public function settingsFilePath(): string
    {
        return rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'config'
        . DIRECTORY_SEPARATOR
        . 'workspace.php';
    }

    /**
     * HR: Vraća root direktorij modula.
     * EN: Returns the module root directory.
     */
    public function moduleRoot(): string
    {
        return $this->moduleRoot;
    }

    /**
     * HR: Čita jednu konfiguracijsku sekciju kao string-key polje.
     * EN: Reads one configuration section as a string-key array.
     *
     * @return array<string, mixed>
     */
    private function section(string $key): array
    {
        $section = $this->workspaceConfig[$key] ?? [];

        return WorkspaceValue::stringKeyArray($section);
    }

    /**
     * HR: Učitava zadane postavke i opcionalni override host aplikacije.
     * EN: Loads defaults and the optional host-application override.
     *
     * @return array<string, mixed>
     */
    private function loadWorkspaceConfig(): array
    {
        $defaults = WorkspaceValue::stringKeyArray(
            require $this->moduleRoot . '/config/workspace.php',
        );

        $appConfigPath = $this->settingsFilePath();
        if (!is_file($appConfigPath)) {
            return $defaults;
        }

        $override = WorkspaceValue::stringKeyArray(require $appConfigPath);

        return WorkspaceValue::stringKeyArray(array_replace_recursive($defaults, $override));
    }
}
