<?php

declare(strict_types=1);

/**
 * HR: Dijeljeni bočni izbornik koristi renderer menu modula kada postoji, a inače prikazuje samostalni fallback.
 * EN: The shared sidebar uses the menu module renderer when available and otherwise displays a standalone fallback.
 *
 * @var \HeartPhrame\View\View $this
 * @var string $settingsPath
 * @var string $allPath
 * @var string $deletedPath
 * @var string $settingsMenuActiveSection
 * @var object|null $menuRenderer
 */

$settingsMenuHtml = null;
if (isset($menuRenderer) && is_object($menuRenderer)) {
    $settingsMenuCallback = [$menuRenderer, 'renderSettingsMenu'];
    if (is_callable($settingsMenuCallback)) {
        $renderedSettingsMenu = $settingsMenuCallback($settingsMenuActiveSection);
        $settingsMenuHtml = is_string($renderedSettingsMenu) ? $renderedSettingsMenu : null;
    }
}
?>
<?php if ($settingsMenuHtml !== null) : ?>
    <?= $settingsMenuHtml ?>
<?php else : ?>
    <nav class="card" aria-label="<?= $this->escape(__('Postavke područja')) ?>">
        <div class="card-body">
            <h2 class="h5 mb-3"><?= $this->escape(__('Postavke')) ?></h2>
            <div class="list-group list-group-flush">
                <a
                    class="list-group-item list-group-item-action
    <?= $settingsMenuActiveSection === 'workspace.settings' ? 'active' : '' ?>"
                    href="<?= $this->escape($settingsPath) ?>"
    <?= $settingsMenuActiveSection === 'workspace.settings' ? 'aria-current="page"' : '' ?>
                >
    <?= $this->escape(__('Postavke područja')) ?>
                </a>
                <div class="list-group-item text-muted small fw-semibold">
    <?= $this->escape(__('Područja')) ?>
                </div>
                <a
                    class="list-group-item list-group-item-action ps-4
    <?= $settingsMenuActiveSection === 'workspace.settings.all' ? 'active' : '' ?>"
                    href="<?= $this->escape($allPath) ?>"
    <?= $settingsMenuActiveSection === 'workspace.settings.all' ? 'aria-current="page"' : '' ?>
                >
    <?= $this->escape(__('Sva područja')) ?>
                </a>
                <a
                    class="list-group-item list-group-item-action ps-4
    <?= $settingsMenuActiveSection === 'workspace.settings.deleted' ? 'active' : '' ?>"
                    href="<?= $this->escape($deletedPath) ?>"
    <?= $settingsMenuActiveSection === 'workspace.settings.deleted' ? 'aria-current="page"' : '' ?>
                >
    <?= $this->escape(__('Obrisana područja')) ?>
                </a>
            </div>
        </div>
    </nav>
<?php endif; ?>
