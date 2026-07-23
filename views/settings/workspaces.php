<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var list<array<string, mixed>> $workspaces
 * @var bool $deleted
 * @var bool $tablesReady
 * @var string $restorePath
 * @var string $settingsPath
 * @var string $allPath
 * @var string $deletedPath
 * @var string $newPath
 * @var string $settingsMenuActiveSection
 * @var object|null $menuRenderer
 * @var string $assetsCssPath
 */
?>
<link rel="stylesheet" href="<?= $this->escape($assetsCssPath) ?>">

<div class="row g-4">
    <aside class="col-lg-3">
        <?php require __DIR__ . '/sidebar.php'; ?>
    </aside>

    <div class="col-lg-9">
        <section class="card">
            <div class="card-body">
                <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?= $this->escape($title) ?></h1>
                        <p class="text-body-secondary mb-0">
                            <?= $this->escape(
                                $deleted
                                ? __('Vraćanje prethodno obrisanih područja.')
                                : __('Upravljanje aktivnim područjima i njihovim sadržajem.'),
                            ) ?>
                        </p>
                    </div>
                    <?php if (!$deleted) : ?>
                        <a class="btn btn-primary" href="<?= $this->escape($newPath) ?>">
                        <?= $this->escape(__('Novo područje')) ?>
                        </a>
                    <?php endif; ?>
                </header>

                <?php if (!$tablesReady) : ?>
                    <div class="alert alert-warning mb-0">
                    <?= $this->escape(__('Workspace migracija još nije pokrenuta.')) ?>
                    </div>
                <?php elseif ($workspaces === []) : ?>
                    <div class="border rounded p-4 text-body-secondary">
                    <?= $this->escape(
                        $deleted ? __('Nema obrisanih područja.') : __('Nema kreiranih područja.'),
                    ) ?>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= $this->escape(__('Naziv')) ?></th>
                                    <th scope="col"><?= $this->escape(__('Slug')) ?></th>
                                    <th scope="col"><?= $this->escape(__('Vidljivost')) ?></th>
                                    <th scope="col" class="text-end"><?= $this->escape(__('Akcije')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php foreach ($workspaces as $workspace) : ?>
                        <?php
                        $name = WorkspaceValue::string($workspace['name'] ?? '');
                        $slug = WorkspaceValue::string($workspace['slug'] ?? '');
                        $visibility = WorkspaceValue::string($workspace['visibility'] ?? 'restricted');
                        $publicPath = WorkspaceValue::string($workspace['public_path'] ?? '#');
                        $managePath = WorkspaceValue::string($workspace['manage_path'] ?? '#');
                        ?>
                                <tr>
                                    <th scope="row"><?= $this->escape($name) ?></th>
                                    <td class="font-monospace"><?= $this->escape($slug) ?></td>
                                    <td><?= $this->escape(__($visibility)) ?></td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-2">
                        <?php if ($deleted) : ?>
                                            <form method="post" action="<?= $this->escape($restorePath) ?>">
                            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                                                <input
                                                    type="hidden"
                                                    name="workspace_id"
                                                    value="<?= WorkspaceValue::int($workspace['id'] ?? 0) ?>"
                                                >
                                                <div class="input-group input-group-sm">
                                                    <input
                                                        class="form-control font-monospace"
                                                        name="slug"
                                                        value="<?= $this->escape($slug) ?>"
                                                        aria-label="<?= $this->escape(__('Slug za vraćanje')) ?>"
                                                    >
                                                    <button class="btn btn-primary" type="submit">
                            <?= $this->escape(__('Vrati')) ?>
                                                    </button>
                                                </div>
                                            </form>
                        <?php else : ?>
                                            <a
                                                class="btn btn-sm btn-secondary"
                                                href="<?= $this->escape($publicPath) ?>"
                                            >
                            <?= $this->escape(__('Otvori')) ?>
                                            </a>
                                            <a
                                                class="btn btn-sm btn-primary"
                                                href="<?= $this->escape($managePath) ?>"
                                            >
                            <?= $this->escape(__('Upravljaj')) ?>
                                            </a>
                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
