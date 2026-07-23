<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

// phpcs:disable Generic.WhiteSpace.ScopeIndent

/**
 * @var \HeartPhrame\View\View $this
 * @var array<string, mixed> $workspace
 * @var array<string, bool> $workspacePermissions
 * @var list<array<string, mixed>> $tree
 * @var array<string, mixed>|null $activeNode
 * @var array<string, mixed>|null $editorView
 * @var array<string, mixed>|null $workflow
 * @var string $workflowTransitionPath
 * @var list<array{title:string,href:string,submittedAt:string}> $reviewQueue
 * @var list<array{title:string,href:string,status:string,statusLabel:string,updatedAt:string}> $unpublishedPages
 * @var list<array<string, mixed>> $fallbackLeadingActions
 * @var string $language
 * @var bool $treeVisibleByDefault
 * @var string $managePath
 * @var string $pageCreatePath
 * @var list<array{id:int,label:string}> $pageParentOptions
 * @var int $defaultPageParentId
 * @var bool $canCreatePage
 * @var bool $canOrganizeTree
 * @var list<array<string, mixed>> $managementNodes
 * @var string $nodeSavePath
 * @var string $nodeDialogPath
 * @var string $treeOrderSavePath
 * @var list<array{id:string,title:string}> $editorDocuments
 * @var bool $editorAvailable
 * @var bool $canAttachExistingDocuments
 * @var string $assetsCssPath
 * @var string $assetsJsPath
 */
$activeNodeId = is_array($activeNode ?? null) ? WorkspaceValue::int($activeNode['id'] ?? 0) : null;
$canManageContent = ($workspacePermissions['can_add'] ?? false)
|| ($workspacePermissions['can_edit'] ?? false)
|| ($workspacePermissions['can_delete'] ?? false)
|| ($workspacePermissions['can_manage'] ?? false);
$manageLabel = ($workspacePermissions['can_manage'] ?? false)
? __('Upravljaj područjem')
: __('Upravljaj sadržajem');
$reviewQueue = is_array($reviewQueue ?? null) ? array_values($reviewQueue) : [];
$unpublishedPages = is_array($unpublishedPages ?? null) ? array_values($unpublishedPages) : [];
$hasTreeActions = $canCreatePage
    || $canManageContent
    || $reviewQueue !== []
    || $unpublishedPages !== [];
$fallbackLeadingActions = is_array($fallbackLeadingActions ?? null)
    ? array_values($fallbackLeadingActions)
    : [];
$workflowIcon = static function (string $action): string {
    $paths = match ($action) {
        'submit' => '<path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4z"/>',
        'withdraw', 'restore_draft' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>'
            . '<path d="M3 3v5h5"/>',
        'publish' => '<path d="M20 6 9 17l-5-5"/>',
        'archive' => '<path d="M3 6h18"/><path d="M5 6v14h14V6"/>'
            . '<path d="M8 3h8l2 3H6z"/><path d="M9 10h6"/>',
        'discard', 'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/>'
            . '<path d="M19 6l-1 15H6L5 6"/><path d="M10 11v6M14 11v6"/>',
        'draft' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
            . '<path d="M14 2v6h6"/><path d="M8 15h8M8 11h3"/>',
        'view' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12"/>'
            . '<circle cx="12" cy="12" r="3"/>',
        'tree' => '<path d="M10 3H5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/>'
            . '<path d="M19 14h-5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2z"/>'
            . '<path d="M7 10v2a2 2 0 0 0 2 2h5"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="workspace-workflow-action-icon" viewBox="0 0 24 24"'
        . ' aria-hidden="true" focusable="false">' . $paths . '</svg>';
};
?>
<link rel="stylesheet" href="<?= $this->escape($assetsCssPath) ?>">
<script src="<?= $this->escape($assetsJsPath) ?>" defer></script>

<div class="workspace-shell">
    <aside
        id="workspace-page-tree"
        class="workspace-sidebar collapse<?= $treeVisibleByDefault ? ' show' : '' ?>"
        aria-label="<?= $this->escape(__('Stablo stranica')) ?>"
    >
        <nav class="card shadow-sm workspace-tree-card" aria-label="<?= $this->escape(__('Stablo stranica')) ?>">
            <div class="card-body">
                <div class="workspace-tree-heading mb-3">
                    <?php if ($hasTreeActions) : ?>
                        <div
                            class="workspace-tree-card-actions"
                            aria-label="<?= $this->escape(__('Akcije područja')) ?>"
                        >
                        <?php if ($unpublishedPages !== []) : ?>
                                <button
                                    class="btn btn-outline-info btn-sm workspace-tree-card-action
                                        workspace-tree-card-action-count"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#workspace-unpublished-pages-modal"
                                    title="<?= $this->escape(__('Nove neobjavljene stranice')) ?>"
                                    aria-label="<?= $this->escape(__('Nove neobjavljene stranice')) ?>"
                                >
                                    <?= $workflowIcon('draft') ?>
                                    <span class="badge text-bg-info workspace-unpublished-page-count">
                                        <?= count($unpublishedPages) ?>
                                    </span>
                                </button>
                        <?php endif; ?>
                        <?php if ($reviewQueue !== []) : ?>
                                <button
                                    class="btn btn-outline-warning btn-sm workspace-tree-card-action
                                        workspace-tree-card-action-count"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#workspace-review-queue-modal"
                                    title="<?= $this->escape(__('Poslano na pregled')) ?>"
                                    aria-label="<?= $this->escape(__('Poslano na pregled')) ?>"
                                >
                                    <?= $workflowIcon('submit') ?>
                                    <span class="badge text-bg-warning workspace-review-queue-count">
                                        <?= count($reviewQueue) ?>
                                    </span>
                                </button>
                        <?php endif; ?>
                        <?php if ($canCreatePage) : ?>
                                <button
                                    class="btn btn-outline-primary btn-sm workspace-tree-card-action"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#workspace-create-page"
                                    aria-controls="workspace-create-page"
                                    aria-expanded="false"
                                    title="<?= $this->escape(__('Nova stranica')) ?>"
                                    aria-label="<?= $this->escape(__('Nova stranica')) ?>"
                                >
                                    <svg
                                        class="workspace-tree-card-action-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path d="M14 2v6h6M12 18v-6M9 15h6"/>
                                    </svg>
                                </button>
                        <?php endif; ?>
                        <?php if ($canOrganizeTree) : ?>
                                <button
                                    class="btn btn-outline-secondary btn-sm workspace-tree-card-action"
                                    type="button"
                                    data-workspace-tree-edit-toggle
                                    title="<?= $this->escape(__('Uredi stablo')) ?>"
                                    aria-label="<?= $this->escape(__('Uredi stablo')) ?>"
                                    aria-pressed="false"
                                >
                                    <svg
                                        class="workspace-tree-card-action-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4z"/>
                                    </svg>
                                </button>
                        <?php endif; ?>
                        <?php if ($canManageContent) : ?>
                                <a
                                    class="btn btn-outline-secondary btn-sm workspace-tree-card-action"
                                    href="<?= $this->escape($managePath) ?>"
                                    title="<?= $this->escape($manageLabel) ?>"
                                    aria-label="<?= $this->escape($manageLabel) ?>"
                                >
                                    <svg
                                        class="workspace-tree-card-action-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path
                                            d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4"
                                        />
                                    </svg>
                                </a>
                        <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="h6 text-uppercase text-muted mb-0 workspace-tree-title">
                        <?= $this->escape(WorkspaceValue::string($workspace['name'] ?? '')) ?>
                    </h2>
                </div>
                <div
                    class="list-group list-group-flush workspace-tree"
                    data-workspace-tree-view
                >
                    <?php if ($tree === []) : ?>
                        <p class="small text-body-secondary mb-0">
                        <?= $this->escape(__('Stablo je prazno.')) ?>
                        </p>
                    <?php else : ?>
                        <?= $this->forModulePartial(
                            'aaieduhr/heartphrame-module-workspace',
                            'workspace/tree',
                            ['nodes' => $tree, 'activeNodeId' => $activeNodeId, 'level' => 1],
                        ) ?>
                    <?php endif; ?>
                </div>
                <?php if ($canOrganizeTree) : ?>
                    <div data-workspace-tree-editor hidden>
                        <?= $this->forModulePartial(
                            'aaieduhr/heartphrame-module-workspace',
                            'workspace/tree-organizer',
                            [
                                'workspace' => $workspace,
                                'nodes' => $managementNodes,
                                'activeNodeId' => $activeNodeId,
                                'treeOrderSavePath' => $treeOrderSavePath,
                                'nodeDialogPath' => $nodeDialogPath,
                            ],
                        ) ?>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </aside>

    <main class="workspace-main">
        <?php if ($canCreatePage) : ?>
            <div id="workspace-create-page" class="collapse">
                <form
                    class="workspace-page-create border rounded bg-body-tertiary p-3 mb-4"
                    method="post"
                    action="<?= $this->escape($pageCreatePath) ?>"
                >
            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                    <input
                        type="hidden"
                        name="workspace_id"
                        value="<?= WorkspaceValue::int($workspace['id'] ?? 0) ?>"
                    >
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?= $this->escape(__('Nova stranica')) ?></h2>
                            <p class="small text-body-secondary mb-0">
            <?= $this->escape(__('Nakon kreiranja otvorit će se HTML editor.')) ?>
                            </p>
                        </div>
                        <button
                            class="btn btn-sm btn-secondary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#workspace-create-page"
                            aria-controls="workspace-create-page"
                        >
            <?= $this->escape(__('Odustani')) ?>
                        </button>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-5">
                            <label class="form-label" for="workspace-page-title">
            <?= $this->escape(__('Naslov stranice')) ?>
                            </label>
                            <input
                                id="workspace-page-title"
                                class="form-control"
                                name="title"
                                required
                            >
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="workspace-page-slug">
            <?= $this->escape(__('Slug')) ?>
                            </label>
                            <input
                                id="workspace-page-slug"
                                class="form-control font-monospace"
                                name="slug"
                            >
                            <div class="form-text">
            <?= $this->escape(__('Ako ostane prazan, slug se izrađuje iz naslova.')) ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label" for="workspace-page-parent">
            <?= $this->escape(__('Nadređena stranica')) ?>
                            </label>
                            <select id="workspace-page-parent" class="form-select" name="parent_id">
                                <option value=""><?= $this->escape(__('Korijen stabla')) ?></option>
            <?php foreach ($pageParentOptions as $parentOption) : ?>
                                    <option
                                        value="<?= WorkspaceValue::int($parentOption['id'] ?? 0) ?>"
                <?= $defaultPageParentId === WorkspaceValue::int(
                    $parentOption['id'] ?? 0,
                ) ? 'selected' : '' ?>
                                    >
                <?= $this->escape(
                    WorkspaceValue::string($parentOption['label'] ?? ''),
                ) ?>
                                    </option>
            <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">
            <?= $this->escape(__('Kreiraj i uredi')) ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (is_array($editorView ?? null)) : ?>
            <?= $this->forModulePartial(
                'aaieduhr/heartphrame-module-editor-html',
                'editor/view',
                $editorView,
            ) ?>
        <?php elseif (is_array($activeNode ?? null)) : ?>
            <article class="card shadow-sm">
                <div class="card-body">
                    <div class="editor-html-view-actions workspace-unpublished-actions">
                    <?php foreach ($fallbackLeadingActions as $action) : ?>
                        <?php $actionType = WorkspaceValue::string($action['type'] ?? ''); ?>
                        <?php if ($actionType === 'collapse') : ?>
                            <button
                                class="btn btn-outline-secondary btn-sm workspace-tree-card-action"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="<?= $this->escape(
                                    WorkspaceValue::string($action['target'] ?? ''),
                                ) ?>"
                                title="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                                aria-label="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                            >
                                <?= $workflowIcon('tree') ?>
                            </button>
                        <?php elseif ($actionType === 'link') : ?>
                            <a
                                class="btn btn-outline-warning btn-sm workspace-tree-card-action"
                                href="<?= $this->escape(WorkspaceValue::string($action['href'] ?? '')) ?>"
                                title="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                                aria-label="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                            >
                                <?= $workflowIcon(WorkspaceValue::string($action['icon'] ?? 'draft')) ?>
                            </a>
                        <?php elseif ($actionType === 'form') : ?>
                            <form method="post" action="<?= $this->escape(
                                WorkspaceValue::string($action['path'] ?? ''),
                            ) ?>">
                                <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                            <?php
                            $actionFields = WorkspaceValue::stringKeyArray($action['fields'] ?? null);
                            ?>
                            <?php foreach ($actionFields as $name => $value) : ?>
                                <input
                                    type="hidden"
                                    name="<?= $this->escape($name) ?>"
                                    value="<?= $this->escape(WorkspaceValue::string($value)) ?>"
                                >
                            <?php endforeach; ?>
                                <button
                                    class="btn btn-outline-<?= $this->escape(
                                        WorkspaceValue::string($action['style'] ?? 'secondary'),
                                    ) ?> btn-sm workspace-tree-card-action"
                                    type="submit"
                                    title="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                                    aria-label="<?= $this->escape(WorkspaceValue::string($action['label'] ?? '')) ?>"
                                >
                                    <?= $workflowIcon(WorkspaceValue::string($action['icon'] ?? '')) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                    <h1 class="h2"><?= $this->escape(WorkspaceValue::string($activeNode['title'] ?? '')) ?></h1>
                    <p class="text-body-secondary mb-0">
                        <?= $this->escape(__('Stranica još nije objavljena.')) ?>
                    </p>
                </div>
            </article>
        <?php else : ?>
            <button
                class="btn btn-outline-secondary btn-sm workspace-fallback-tree-action mb-3"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#workspace-page-tree"
                aria-controls="workspace-page-tree"
                aria-expanded="<?= $treeVisibleByDefault ? 'true' : 'false' ?>"
                title="<?= $this->escape(__('Stablo')) ?>"
                aria-label="<?= $this->escape(__('Stablo')) ?>"
            >
                <svg class="workspace-tree-card-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M10 3H5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/>
                    <path d="M19 14h-5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2z"/>
                    <path d="M7 10v2a2 2 0 0 0 2 2h5"/>
                </svg>
            </button>
            <header class="mb-4">
                <h1 class="h2 mb-2"><?= $this->escape(WorkspaceValue::string($workspace['name'] ?? '')) ?></h1>
            <?php if (WorkspaceValue::string($workspace['description'] ?? '') !== '') : ?>
                    <p class="text-body-secondary mb-0">
                <?= nl2br($this->escape(WorkspaceValue::string($workspace['description'] ?? ''))) ?>
                    </p>
            <?php endif; ?>
            </header>
            <p class="text-body-secondary">
            <?= $this->escape(__('Odaberite stranicu iz stabla ili postavite početnu stranicu područja.')) ?>
            </p>
        <?php endif; ?>
    </main>
</div>

<?php if ($unpublishedPages !== []) : ?>
    <div
        class="modal fade"
        id="workspace-unpublished-pages-modal"
        tabindex="-1"
        aria-labelledby="workspace-unpublished-pages-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="workspace-unpublished-pages-title" class="modal-title fs-5">
                        <?= $this->escape(__('Nove neobjavljene stranice')) ?>
                    </h2>
                    <button
                        class="btn-close"
                        type="button"
                        data-bs-dismiss="modal"
                        aria-label="<?= $this->escape(__('Zatvori')) ?>"
                    ></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                    <?php foreach ($unpublishedPages as $unpublishedPage) : ?>
                        <a
                            class="list-group-item list-group-item-action
                                d-flex align-items-center justify-content-between gap-3"
                            href="<?= $this->escape(WorkspaceValue::string($unpublishedPage['href'] ?? '')) ?>"
                        >
                            <span class="fw-semibold">
                                <?= $this->escape(WorkspaceValue::string($unpublishedPage['title'] ?? '')) ?>
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-<?= WorkspaceValue::string(
                                    $unpublishedPage['status'] ?? '',
                                ) === 'in_review' ? 'warning' : 'info' ?>">
                                    <?= $this->escape(WorkspaceValue::string(
                                        $unpublishedPage['statusLabel'] ?? '',
                                    )) ?>
                                </span>
                            <?php if (WorkspaceValue::string($unpublishedPage['updatedAt'] ?? '') !== '') : ?>
                                <span class="small text-body-secondary text-nowrap">
                                    <?= $this->escape(WorkspaceValue::string(
                                        $unpublishedPage['updatedAt'] ?? '',
                                    )) ?>
                                </span>
                            <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                        <?= $this->escape(__('Zatvori')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($reviewQueue !== []) : ?>
    <div
        class="modal fade"
        id="workspace-review-queue-modal"
        tabindex="-1"
        aria-labelledby="workspace-review-queue-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="workspace-review-queue-title" class="modal-title fs-5">
                        <?= $this->escape(__('Poslano na pregled')) ?>
                    </h2>
                    <button
                        class="btn-close"
                        type="button"
                        data-bs-dismiss="modal"
                        aria-label="<?= $this->escape(__('Zatvori')) ?>"
                    ></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                    <?php foreach ($reviewQueue as $reviewItem) : ?>
                        <a
                            class="list-group-item list-group-item-action
                                d-flex align-items-center justify-content-between gap-3"
                            href="<?= $this->escape(WorkspaceValue::string($reviewItem['href'] ?? '')) ?>"
                        >
                            <span class="fw-semibold">
                                <?= $this->escape(WorkspaceValue::string($reviewItem['title'] ?? '')) ?>
                            </span>
                        <?php if (WorkspaceValue::string($reviewItem['submittedAt'] ?? '') !== '') : ?>
                            <span class="small text-body-secondary text-nowrap">
                                <?= $this->escape(WorkspaceValue::string($reviewItem['submittedAt'] ?? '')) ?>
                            </span>
                        <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                        <?= $this->escape(__('Zatvori')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canOrganizeTree) : ?>
    <div
        class="modal fade"
        id="workspace-add-tree-item-modal"
        tabindex="-1"
        aria-labelledby="workspace-add-tree-item-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 id="workspace-add-tree-item-title" class="modal-title fs-5 mb-0">
                            <?= $this->escape(__('Dodaj poveznicu ili postojeći dokument')) ?>
                        </h2>
                        <p class="small text-body-secondary mb-0">
                            <?= $this->escape(__('Nova stavka bit će dodana u stablo ovog područja.')) ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="<?= $this->escape(__('Zatvori')) ?>"
                    ></button>
                </div>
                <form method="post" action="<?= $this->escape($nodeSavePath) ?>">
                    <div class="modal-body">
                        <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                        <input
                            type="hidden"
                            name="workspace_id"
                            value="<?= WorkspaceValue::int($workspace['id'] ?? 0) ?>"
                        >
                        <input type="hidden" name="return_context" value="workspace">
                        <input
                            type="hidden"
                            name="return_node_id"
                            value="<?= WorkspaceValue::int($activeNodeId ?? 0) ?>"
                        >
                        <?= $this->forModulePartial(
                            'aaieduhr/heartphrame-module-workspace',
                            'workspace/node-fields',
                            [
                                'node' => ['node_type' => 'internal_link'],
                                'nodes' => $managementNodes,
                                'editorDocuments' => $editorDocuments,
                                'editorAvailable' => $editorAvailable,
                                'canAttachExistingDocuments' => $canAttachExistingDocuments,
                                'workspaceCanAdd' => (bool)($workspacePermissions['can_add'] ?? false),
                                'treeOrganizerAvailable' => true,
                            ],
                        ) ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= $this->escape(__('Odustani')) ?>
                        </button>
                        <button class="btn btn-primary" type="submit">
                            <?= $this->escape(__('Dodaj stavku')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div
        class="modal fade"
        id="workspace-node-editor-modal"
        tabindex="-1"
        aria-labelledby="workspace-node-editor-loading-title"
        aria-hidden="true"
        data-workspace-node-editor-modal
        data-workspace-node-dialog-loading="<?= $this->escape(__('Učitavanje...')) ?>"
        data-workspace-node-dialog-error="<?= $this->escape(
            __('Postavke stavke nije moguće učitati.'),
        ) ?>"
        data-workspace-node-dialog-close="<?= $this->escape(__('Zatvori')) ?>"
    >
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="workspace-node-editor-loading-title" class="modal-title fs-5">
                        <?= $this->escape(__('Uredi stavku')) ?>
                    </h2>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="<?= $this->escape(__('Zatvori')) ?>"
                    ></button>
                </div>
                <div class="modal-body">
                    <p class="text-body-secondary mb-0" data-workspace-node-dialog-status>
                        <?= $this->escape(__('Učitavanje...')) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
