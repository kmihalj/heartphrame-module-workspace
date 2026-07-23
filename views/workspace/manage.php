<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

// phpcs:disable Generic.WhiteSpace.ScopeIndent,Squiz.ControlStructures.ControlSignature,Generic.Files.LineLength

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var array<string, mixed>|null $workspace
 * @var array<string, bool> $workspacePermissions
 * @var list<array<string, mixed>> $workspaceAclSubjects
 * @var array<string, mixed>|null $ownerSubject
 * @var bool $isAdministrator
 * @var array<string, mixed>|null $currentUser
 * @var string $savePath
 * @var string $deletePath
 * @var string $aclSavePath
 * @var string $subjectSearchPath
 * @var string $indexPath
 * @var string $workspaceViewPath
 * @var string $assetsCssPath
 * @var string $assetsJsPath
 */
$workspaceId = is_array($workspace ?? null) ? WorkspaceValue::int($workspace['id'] ?? 0) : 0;
$canManage = (bool)($workspacePermissions['can_manage'] ?? false);
$currentUserId = is_array($currentUser ?? null) ? WorkspaceValue::int($currentUser['id'] ?? 0) : 0;
$ownerUserId = WorkspaceValue::int($workspace['owner_user_id'] ?? $currentUserId);
$ownerLabel = is_array($ownerSubject ?? null)
? WorkspaceValue::string($ownerSubject['label'] ?? '')
: WorkspaceValue::string($currentUser['login_identifier'] ?? '');
$subjectsByCategory = ['user' => [], 'group' => []];
foreach ($workspaceAclSubjects as $subject) {
    $category = WorkspaceValue::string($subject['category'] ?? '');
    if (isset($subjectsByCategory[$category])) {
        $subjectsByCategory[$category][] = $subject;
    }
}
?>
<link rel="stylesheet" href="<?= $this->escape($assetsCssPath) ?>">
<script src="<?= $this->escape($assetsJsPath) ?>" defer></script>

<header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1"><?= $this->escape($title) ?></h1>
        <p class="text-body-secondary mb-0">
            <?= $this->escape(__('Podaci područja, članovi i prava.')) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($workspaceViewPath !== '') : ?>
            <a class="btn btn-primary" href="<?= $this->escape($workspaceViewPath) ?>">
                <?= $this->escape(__('Otvori područje')) ?>
            </a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= $this->escape($indexPath) ?>">
            <?= $this->escape(__('Područja')) ?>
        </a>
    </div>
</header>

<?php if ($workspaceId === 0 || $canManage) : ?>
    <section class="card mb-4" aria-labelledby="workspace-data-title">
        <div class="card-body">
            <h2 id="workspace-data-title" class="h5 mb-3">
                <?= $this->escape(__('Podaci područja')) ?>
            </h2>
            <form method="post" action="<?= $this->escape($savePath) ?>">
                <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                <input type="hidden" name="id" value="<?= $workspaceId ?>">
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <label class="form-label" for="workspace-name">
                            <?= $this->escape(__('Naziv')) ?>
                        </label>
                        <input
                            id="workspace-name"
                            class="form-control"
                            name="name"
                            value="<?= $this->escape(WorkspaceValue::string($workspace['name'] ?? '')) ?>"
                            required
                        >
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label" for="workspace-slug">
                            <?= $this->escape(__('Slug')) ?>
                        </label>
                        <input
                            id="workspace-slug"
                            class="form-control font-monospace"
                            name="slug"
                            value="<?= $this->escape(WorkspaceValue::string($workspace['slug'] ?? '')) ?>"
                        >
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label" for="workspace-owner">
                            <?= $this->escape(__('Vlasnik')) ?>
                        </label>
                        <?php if ($isAdministrator) : ?>
                            <div
                                class="workspace-subject-picker"
                                data-workspace-subject-picker
                                data-workspace-picker-mode="owner"
                                data-workspace-subject-type="user"
                                data-workspace-search-url="<?= $this->escape($subjectSearchPath) ?>"
                                data-workspace-id="<?= $workspaceId ?>"
                                data-workspace-no-results="<?= $this->escape(__('Nema rezultata.')) ?>"
                                data-workspace-search-error="<?= $this->escape(__('Pretraživanje nije uspjelo.')) ?>"
                            >
                                <input
                                    type="hidden"
                                    name="owner_user_id"
                                    value="<?= $ownerUserId ?>"
                                    data-workspace-owner-value
                                >
                                <input
                                    id="workspace-owner"
                                    class="form-control"
                                    value="<?= $this->escape($ownerLabel) ?>"
                                    type="search"
                                    role="combobox"
                                    autocomplete="off"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="workspace-owner-results"
                                    data-workspace-subject-search
                                    required
                                >
                                <div
                                    id="workspace-owner-results"
                                    class="workspace-subject-results list-group"
                                    role="listbox"
                                    data-workspace-subject-results
                                    hidden
                                ></div>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="owner_user_id" value="<?= $currentUserId ?>">
                            <input
                                id="workspace-owner"
                                class="form-control"
                                value="<?= $this->escape(
                                    WorkspaceValue::string($currentUser['login_identifier'] ?? ''),
                                ) ?>"
                                disabled
                            >
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="workspace-description">
                            <?= $this->escape(__('Opis')) ?>
                        </label>
                        <textarea
                            id="workspace-description"
                            class="form-control"
                            name="description"
                            rows="2"
                        ><?= $this->escape(WorkspaceValue::string($workspace['description'] ?? '')) ?></textarea>
                    </div>
                    <?php if ($workspaceId > 0) : ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input
                                    id="workspace-archived"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_archived"
                                    value="1"
                                    <?= (bool)($workspace['is_archived'] ?? false) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="workspace-archived">
                                    <?= $this->escape(__('Arhivirano područje je samo za čitanje')) ?>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <?= $this->escape(__('Spremi')) ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if ($workspaceId > 0) : ?>
    <?php if ($canManage) : ?>
        <section class="card mb-4" aria-labelledby="workspace-acl-title">
            <div class="card-body">
                <h2 id="workspace-acl-title" class="h5 mb-1">
                    <?= $this->escape(__('Članovi i prava')) ?>
                </h2>
                <p class="small text-body-secondary mb-3">
                    <?= $this->escape(
                        __(
                            'Prava korisnika i njegovih grupa se zbrajaju. '
                            . 'Dodajte samo potrebne subjekte; upravljanje uključuje sva prava.',
                        ),
                    ) ?>
                </p>
                <form
                    method="post"
                    action="<?= $this->escape($aclSavePath) ?>"
                    data-workspace-acl-form
                    data-workspace-remove-label="<?= $this->escape(__('Ukloni')) ?>"
                    data-workspace-built-in-label="<?= $this->escape(__('Ugrađeno')) ?>"
                    <?php foreach (['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'] as $permission) : ?>
                        data-workspace-permission-<?= str_replace('_', '-', $permission) ?>-label="<?= $this->escape(
                            __($permission),
                        ) ?>"
                    <?php endforeach; ?>
                >
                    <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                    <input type="hidden" name="workspace_id" value="<?= $workspaceId ?>">

                    <?php foreach (['user', 'group'] as $category) : ?>
                        <?php $subjects = $subjectsByCategory[$category]; ?>
                        <section
                            class="workspace-acl-subject-section mt-3"
                            data-workspace-acl-section="<?= $category ?>"
                        >
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-12 col-lg-5">
                                    <label
                                        class="form-label"
                                        for="workspace-acl-search-<?= $category ?>"
                                    >
                                        <?= $this->escape(
                                            $category === 'user' ? __('Dodaj korisnika') : __('Dodaj grupu'),
                                        ) ?>
                                    </label>
                                    <div
                                        class="workspace-subject-picker"
                                        data-workspace-subject-picker
                                        data-workspace-picker-mode="acl"
                                        data-workspace-subject-type="<?= $category ?>"
                                        data-workspace-search-url="<?= $this->escape($subjectSearchPath) ?>"
                                        data-workspace-id="<?= $workspaceId ?>"
                                        data-workspace-no-results="<?= $this->escape(__('Nema rezultata.')) ?>"
                                        data-workspace-search-error="<?= $this->escape(
                                            __('Pretraživanje nije uspjelo.'),
                                        ) ?>"
                                    >
                                        <input
                                            id="workspace-acl-search-<?= $category ?>"
                                            class="form-control"
                                            type="search"
                                            role="combobox"
                                            autocomplete="off"
                                            aria-autocomplete="list"
                                            aria-expanded="false"
                                            aria-controls="workspace-acl-results-<?= $category ?>"
                                            placeholder="<?= $this->escape(
                                                $category === 'user'
                                                ? __('Pretraži korisnike')
                                                : __('Pretraži grupe'),
                                            ) ?>"
                                            data-workspace-subject-search
                                        >
                                        <div
                                            id="workspace-acl-results-<?= $category ?>"
                                            class="workspace-subject-results list-group"
                                            role="listbox"
                                            data-workspace-subject-results
                                            hidden
                                        ></div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-7">
                                    <p class="small text-body-secondary mb-1">
                                        <?= $this->escape(
                                            $category === 'user'
                                            ? __('Pretražite po imenu ili korisničkoj oznaci.')
                                            : __(
                                                'Javno i Svi prijavljeni ugrađene su publike, '
                                                . 'a ne Auth grupe.',
                                            ),
                                        ) ?>
                                    </p>
                                </div>
                            </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle workspace-acl-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= $this->escape(__('Naziv')) ?></th>
                                        <?php foreach (['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'] as $permission) : ?>
                                            <th scope="col" class="text-center">
                                                <?= $this->escape(__($permission)) ?>
                                            </th>
                                        <?php endforeach; ?>
                                        <th scope="col" class="workspace-acl-action-column">
                                            <span class="visually-hidden"><?= $this->escape(__('Radnje')) ?></span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody data-workspace-acl-rows="<?= $category ?>">
                                    <?php foreach ($subjects as $subject) : ?>
                                        <?php
                                        $subjectType = WorkspaceValue::string($subject['subject_type'] ?? '');
                                        $subjectId = WorkspaceValue::int($subject['subject_id'] ?? 0);
                                        $label = WorkspaceValue::string($subject['label'] ?? '');
                                        $builtIn = (bool)($subject['is_builtin'] ?? false);
                                        $publicReadOnly = (bool)($subject['is_read_only'] ?? false);
                                        ?>
                                        <tr data-workspace-acl-row="<?= $subjectType ?>:<?= $subjectId ?>">
                                            <th scope="row">
                                                <?= $this->escape($label) ?>
                                                <?php if ($builtIn) : ?>
                                                    <span class="badge text-bg-secondary ms-1">
                                                        <?= $this->escape(__('Ugrađeno')) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </th>
                                            <?php foreach (['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'] as $permission) : ?>
                                                <td class="text-center">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="acl[<?= $subjectType ?>][<?= $subjectId ?>][<?= $permission ?>]"
                                                        value="1"
                                                        <?= $publicReadOnly && $permission !== 'can_view' ? 'disabled' : '' ?>
                                                        aria-label="<?= $this->escape(
                                                            __($permission) . ': ' . $label,
                                                        ) ?>"
                                                        <?= (bool)($subject[$permission] ?? false) ? 'checked' : '' ?>
                                                    >
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="text-end">
                                                <button
                                                    class="btn btn-sm btn-link text-danger workspace-acl-remove"
                                                    type="button"
                                                    title="<?= $this->escape(__('Ukloni')) ?>"
                                                    aria-label="<?= $this->escape(__('Ukloni') . ': ' . $label) ?>"
                                                    data-workspace-acl-remove
                                                >
                                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                                        <path d="M6 6l12 12M18 6L6 18"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr
                                        class="workspace-acl-empty"
                                        data-workspace-acl-empty
                                        <?= $subjects !== [] ? 'hidden' : '' ?>
                                    >
                                        <td colspan="8" class="text-body-secondary">
                                            <?= $this->escape(__('Nema dodijeljenih subjekata.')) ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </section>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <?= $this->escape(__('Spremi prava')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card mb-4" aria-labelledby="workspace-delete-title">
            <div class="card-body">
                <h2 id="workspace-delete-title" class="h5 mb-2">
                    <?= $this->escape(__('Brisanje područja')) ?>
                </h2>
                <p class="text-body-secondary mb-3">
                    <?= $this->escape(
                        __('Područje se soft-briše i administrator ga može vratiti iz postavki.'),
                    ) ?>
                </p>
                <form
                    method="post"
                    action="<?= $this->escape($deletePath) ?>"
                    onsubmit="return confirm('<?= $this->escape(__('Obrisati ovo područje?')) ?>')"
                >
                    <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                    <input type="hidden" name="workspace_id" value="<?= $workspaceId ?>">
                    <button class="btn btn-danger" type="submit">
                        <?= $this->escape(__('Obriši područje')) ?>
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
