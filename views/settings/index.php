<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var array<string, mixed> $settings
 * @var string $savePath
 * @var string $settingsPath
 * @var string $allPath
 * @var string $deletedPath
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
        <form method="post" action="<?= $this->escape($savePath) ?>">
            <section class="card">
                <div class="card-body">
                    <header class="mb-4">
                        <h1 class="h3 mb-1"><?= $this->escape($title) ?></h1>
                        <p class="text-body-secondary mb-0">
                            <?= $this->escape(__('Opće ponašanje URL-ova, stabla i novih područja.')) ?>
                        </p>
                    </header>

                    <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                    <div class="row g-3">
                        <div class="col-12 col-lg-5">
                            <label class="form-label" for="workspace-root-path">
                                <?= $this->escape(__('Korijenska putanja područja')) ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input
                                    id="workspace-root-path"
                                    class="form-control font-monospace"
                                    name="root_path"
                                    value="<?= $this->escape(
                                        WorkspaceValue::string($settings['root_path'] ?? 'workspace'),
                                    ) ?>"
                                    required
                                >
                                <span class="input-group-text">/{workspace}/{page}</span>
                            </div>
                            <div class="form-text">
                                <?= $this->escape(__('Putanja mora imati slobodan prvi segment aplikacije.')) ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="workspace-default-visibility">
                                <?= $this->escape(__('Zadana vidljivost')) ?>
                            </label>
                            <select
                                id="workspace-default-visibility"
                                class="form-select"
                                name="default_visibility"
                            >
                                <?php foreach (['restricted', 'authenticated', 'public'] as $visibility) : ?>
                                    <option
                                        value="<?= $visibility ?>"
                                    <?= WorkspaceValue::string(
                                        $settings['default_visibility'] ?? '',
                                    ) === $visibility
                                    ? 'selected'
                                    : '' ?>
                                    >
                                    <?= $this->escape(__($visibility)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label"><?= $this->escape(__('Korisničko sučelje')) ?></label>
                            <div class="form-check form-switch mb-2">
                                <input
                                    id="workspace-tree-visible"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="tree_visible"
                                    value="1"
                                    <?= (bool)($settings['tree_visible'] ?? true) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="workspace-tree-visible">
                                    <?= $this->escape(__('Stablo je početno prikazano')) ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    id="workspace-authenticated-create"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="authenticated_users_may_create"
                                    value="1"
                                    <?= (bool)($settings['authenticated_users_may_create'] ?? false)
                                    ? 'checked'
                                    : '' ?>
                                >
                                <label class="form-check-label" for="workspace-authenticated-create">
                                    <?= $this->escape(
                                        __('Svaki prijavljeni korisnik smije kreirati područje'),
                                    ) ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0" role="note">
                        <strong><?= $this->escape(__('Integracija s HTML editorom')) ?></strong>
                        <div>
                            <?= $this->escape(
                                __(
                                    'Dok su Područja uključena, njihove putanje i ACL nadjačavaju '
                                    . 'samostalnu slug putanju editora.',
                                ),
                            ) ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">
                        <?= $this->escape(__('Spremi postavke')) ?>
                    </button>
                </div>
            </section>
        </form>
    </div>
</div>
