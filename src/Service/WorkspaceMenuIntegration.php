<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use Psr\Container\ContainerInterface;
use Throwable;

use function array_replace;
use function is_callable;
use function is_file;
use function is_object;
use function is_string;
use function method_exists;

final readonly class WorkspaceMenuIntegration
{
    private const MENU_PACKAGE = 'aaieduhr/heartphrame-module-menu';

    private const MENU_REPOSITORY = 'AaiEduHr\\HeartPhrameModuleMenu\\Service\\MenuConfigRepository';

    /**
     * HR: Prima container i konfiguraciju za potpuno opcionalnu module-menu integraciju.
     * EN: Receives the container and configuration for fully optional module-menu integration.
     */
    public function __construct(
        private ContainerInterface $container,
        private WorkspaceConfig $config,
    ) {
    }

    /**
     * HR: Dodaje glavnu i administratorske Workspace stavke kada je menu modul dostupan.
     * EN: Adds main and administration Workspace entries when the menu module is available.
     */
    public function registerMenuItems(): void
    {
        if (
            !$this->config->isAppModuleEnabled(self::MENU_PACKAGE)
            || !class_exists(self::MENU_REPOSITORY)
        ) {
            return;
        }

        try {
            $repository = $this->container->get(self::MENU_REPOSITORY);
        } catch (Throwable) {
            return;
        }

        if (!is_object($repository) || !method_exists($repository, 'jsonPathForSection')) {
            return;
        }

        if ($this->config->shouldAutoRegisterTopMenu()) {
            $this->upsertSection($repository, 'top', [[
                'id' => 'workspaces',
                'parent_id' => '',
                'label' => ['hr' => 'Područja', 'en' => 'Workspaces'],
                'route' => 'workspace.index',
                'url' => '',
                'query' => '',
                'order' => 45,
                'enabled' => true,
                'level' => 0,
            ]]);
        }

        if ($this->config->shouldAutoRegisterSettingsMenu()) {
            $this->upsertSection($repository, 'settings', [
                [
                    'id' => 'workspace.settings.group',
                    'parent_id' => '',
                    'label' => ['hr' => 'Područja', 'en' => 'Workspaces'],
                    'route' => '',
                    'url' => '',
                    'query' => '',
                    'order' => 55,
                    'enabled' => true,
                    'level' => 0,
                ],
                [
                    'id' => 'workspace.settings',
                    'parent_id' => 'workspace.settings.group',
                    'label' => ['hr' => 'Opće postavke', 'en' => 'General settings'],
                    'route' => 'workspace.settings',
                    'url' => '',
                    'query' => '',
                    'order' => 10,
                    'enabled' => true,
                    'level' => 1,
                ],
                [
                    'id' => 'workspace.settings.all',
                    'parent_id' => 'workspace.settings.group',
                    'label' => ['hr' => 'Sva područja', 'en' => 'All workspaces'],
                    'route' => 'workspace.settings.all',
                    'url' => '',
                    'query' => '',
                    'order' => 20,
                    'enabled' => true,
                    'level' => 1,
                ],
                [
                    'id' => 'workspace.settings.deleted',
                    'parent_id' => 'workspace.settings.group',
                    'label' => ['hr' => 'Obrisana područja', 'en' => 'Deleted workspaces'],
                    'route' => 'workspace.settings.deleted',
                    'url' => '',
                    'query' => '',
                    'order' => 30,
                    'enabled' => true,
                    'level' => 1,
                ],
            ]);
        }
    }

    /**
     * HR: Upisuje ili osvježava skup stavki u jednoj menu sekciji.
     * EN: Inserts or refreshes a set of entries in one menu section.
     *
     * @param list<array<string, mixed>> $desired
     */
    private function upsertSection(object $repository, string $section, array $desired): void
    {
        try {
            $pathCallback = [$repository, 'jsonPathForSection'];
            if (!is_callable($pathCallback)) {
                return;
            }

            $path = $pathCallback($section);
            if (!is_string($path) || $path === '') {
                return;
            }

            $items = $this->readTree($path);
            $changed = false;
            foreach ($desired as $item) {
                $changed = $this->upsertTreeItem($items, $item) || $changed;
            }

            if ($changed) {
                $this->writeTree($path, $items);
            }
        } catch (Throwable) {
            return;
        }
    }

    /**
     * HR: Čita menu JSON kao rekurzivnu listu.
     * EN: Reads menu JSON as a recursive list.
     *
     * @return list<array<string, mixed>>
     */
    private function readTree(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return WorkspaceValue::rows($decoded);
    }

    /**
     * HR: Zamjenjuje postojeću stavku ili je dodaje u odgovarajuću roditeljsku granu.
     * EN: Replaces an existing item or appends it to the appropriate parent branch.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $item
     */
    private function upsertTreeItem(array &$items, array $item): bool
    {
        $before = $items;
        $id = WorkspaceValue::string($item['id'] ?? '');
        if ($id === '') {
            return false;
        }

        $matches = $this->extractTreeItemsById($items, $id);
        $existing = $matches[0] ?? [];
        $merged = array_replace($existing, $item);
        if (array_key_exists('order', $existing)) {
            $merged['order'] = $existing['order'];
        }

        $parentId = WorkspaceValue::string($item['parent_id'] ?? '');
        if ($parentId !== '' && $this->appendToParent($items, $parentId, $merged)) {
            return $items !== $before;
        }

        $items[] = $merged;
        return $items !== $before;
    }

    /**
     * HR: Uklanja sve stare pojave jedne stavke iz cijelog stabla i vraća ih za spajanje podataka.
     * Time se popravljaju i ranije duplirane ili pogrešno ugniježđene automatske stavke.
     *
     * EN: Removes every previous occurrence of an item from the complete tree and returns them for merging.
     * This also repairs previously duplicated or incorrectly nested automatic entries.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function extractTreeItemsById(array &$items, string $id): array
    {
        $matches = [];
        $remaining = [];

        foreach ($items as $existing) {
            if (WorkspaceValue::string($existing['id'] ?? '') === $id) {
                $matches[] = $existing;
                continue;
            }

            $children = WorkspaceValue::rows($existing['children'] ?? null);
            if ($children !== []) {
                foreach ($this->extractTreeItemsById($children, $id) as $match) {
                    $matches[] = $match;
                }

                $existing['children'] = $children;
            }

            $remaining[] = $existing;
        }

        $items = $remaining;
        return $matches;
    }

    /**
     * HR: Rekurzivno dodaje menu stavku u pronađenu roditeljsku granu.
     * EN: Recursively appends a menu item into the located parent branch.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $item
     */
    private function appendToParent(array &$items, string $parentId, array $item): bool
    {
        foreach ($items as &$existing) {
            if (WorkspaceValue::string($existing['id'] ?? '') === $parentId) {
                $children = WorkspaceValue::rows($existing['children'] ?? null);
                $children[] = $item;
                $existing['children'] = $children;
                return true;
            }

            $children = WorkspaceValue::rows($existing['children'] ?? null);
            if ($children !== [] && $this->appendToParent($children, $parentId, $item)) {
                $existing['children'] = $children;
                return true;
            }
        }

        return false;
    }

    /**
     * HR: Atomarno sprema uređeno menu stablo kada ga je moguće serijalizirati.
     * EN: Atomically saves the edited menu tree when it can be serialized.
     *
     * @param list<array<string, mixed>> $items
     */
    private function writeTree(string $path, array $items): void
    {
        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            file_put_contents($path, $json . PHP_EOL);
        }
    }
}
