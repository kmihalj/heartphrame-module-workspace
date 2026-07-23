<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

// phpcs:disable Generic.Files.LineLength,Generic.WhiteSpace.ScopeIndent

/**
 * @var \HeartPhrame\View\View $this
 * @var array<string, mixed> $workspace
 * @var list<array<string, mixed>> $workspaceAclSubjects
 * @var array<string, mixed> $node
 * @var list<array<string, mixed>> $nodes
 * @var list<array{id:string,title:string}> $editorDocuments
 * @var bool $editorAvailable
 * @var bool $canAttachExistingDocuments
 * @var string $nodeSavePath
 * @var string $nodeDeletePath
 * @var string $nodeAclSavePath
 * @var int $returnNodeId
 */
$workspaceId = WorkspaceValue::int($workspace['id'] ?? 0);
$nodeId = WorkspaceValue::int($node['id'] ?? 0);
$permissions = WorkspaceValue::stringKeyArray($node['permissions'] ?? null);
$restrictions = WorkspaceValue::rows($node['restrictions'] ?? null);

/**
 * HR: Provjerava jedno spremljeno pravo u ACL recima čvora.
 * EN: Checks one stored permission in the node ACL rows.
 *
 * @param list<array<string, mixed>> $rows
 */
$hasPermission = static function (
    array $rows,
    string $subjectType,
    int $subjectId,
    string $permission,
): bool {
    foreach ($rows as $row) {
        $row = WorkspaceValue::stringKeyArray($row);
        if (
            WorkspaceValue::string($row['subject_type'] ?? '') === $subjectType
            && WorkspaceValue::int($row['subject_id'] ?? 0) === $subjectId
        ) {
            return (bool)($row[$permission] ?? false);
        }
    }

    return false;
};

?>
<div class="modal-header">
    <div>
        <h2 class="modal-title fs-5 mb-0">
            <?= $this->escape(WorkspaceValue::string($node['title'] ?? '')) ?>
        </h2>
        <p class="small text-body-secondary mb-0">
            <?= $this->escape(__('Postavke stavke stabla')) ?>
        </p>
    </div>
    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
        aria-label="<?= $this->escape(__('Zatvori')) ?>"
    ></button>
</div>
<div class="modal-body">
    <?php if ((bool)($permissions['can_edit'] ?? false)) : ?>
        <form method="post" action="<?= $this->escape($nodeSavePath) ?>">
            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
            <input type="hidden" name="workspace_id" value="<?= $workspaceId ?>">
            <input type="hidden" name="id" value="<?= $nodeId ?>">
            <input type="hidden" name="return_context" value="workspace">
            <input type="hidden" name="return_node_id" value="<?= $returnNodeId ?>">
            <?= $this->forModulePartial(
                'aaieduhr/heartphrame-module-workspace',
                'workspace/node-fields',
                [
                    'node' => $node,
                    'nodes' => $nodes,
                    'editorDocuments' => $editorDocuments,
                    'editorAvailable' => $editorAvailable,
                    'canAttachExistingDocuments' => $canAttachExistingDocuments,
                    'workspaceCanAdd' => true,
                    'treeOrganizerAvailable' => true,
                ],
            ) ?>
            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary" type="submit">
                    <?= $this->escape(__('Spremi stavku')) ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ((bool)($permissions['can_manage'] ?? false)) : ?>
        <hr class="my-4">
        <form method="post" action="<?= $this->escape($nodeAclSavePath) ?>">
            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
            <input type="hidden" name="workspace_id" value="<?= $workspaceId ?>">
            <input type="hidden" name="node_id" value="<?= $nodeId ?>">
            <input type="hidden" name="return_context" value="workspace">
            <input type="hidden" name="return_node_id" value="<?= $returnNodeId ?>">
            <h3 class="h6"><?= $this->escape(__('Nasljedna ograničenja')) ?></h3>
            <p class="small text-body-secondary">
                <?= $this->escape(
                    __('Prazna tablica znači da čvor nasljeđuje prava područja bez dodatnog ograničenja.'),
                ) ?>
            </p>
            <?php foreach (['user', 'group'] as $category) : ?>
                <?php
                $eligibleSubjects = array_values(array_filter(
                    $workspaceAclSubjects,
                    static fn(array $subject): bool =>
                        WorkspaceValue::string($subject['category'] ?? '') === $category,
                ));
                ?>
                <?php if ($eligibleSubjects !== []) : ?>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm align-middle workspace-acl-table">
                            <thead>
                                <tr>
                                    <th scope="col">
                                        <?= $this->escape(
                                            $category === 'user' ? __('Korisnici') : __('Grupe'),
                                        ) ?>
                                    </th>
                                    <?php foreach (['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'] as $permission) : ?>
                                        <th scope="col" class="text-center">
                                            <?= $this->escape(__($permission)) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eligibleSubjects as $subject) : ?>
                                    <?php
                                    $subjectType = WorkspaceValue::string($subject['subject_type'] ?? '');
                                    $subjectId = WorkspaceValue::int($subject['subject_id'] ?? 0);
                                    $label = WorkspaceValue::string($subject['label'] ?? '');
                                    $publicReadOnly = (bool)($subject['is_read_only'] ?? false);
                                    ?>
                                    <tr>
                                        <th scope="row"><?= $this->escape($label) ?></th>
                                        <?php foreach (['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'] as $permission) : ?>
                                            <td class="text-center">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="acl[<?= $subjectType ?>][<?= $subjectId ?>][<?= $permission ?>]"
                                                    value="1"
                                                    <?= $publicReadOnly && $permission !== 'can_view' ? 'disabled' : '' ?>
                                                    aria-label="<?= $this->escape(
                                                        __('Nasljedna ograničenja')
                                                        . ' - '
                                                        . __($permission)
                                                        . ': '
                                                        . $label,
                                                    ) ?>"
                                                    <?= $hasPermission(
                                                        $restrictions,
                                                        $subjectType,
                                                        $subjectId,
                                                        $permission,
                                                    ) ? 'checked' : '' ?>
                                                >
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="d-flex justify-content-end">
                <button class="btn btn-secondary" type="submit">
                    <?= $this->escape(__('Spremi ograničenja')) ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ((bool)($permissions['can_delete'] ?? false)) : ?>
        <hr class="my-4">
        <form
            class="d-flex align-items-center justify-content-between gap-3"
            method="post"
            action="<?= $this->escape($nodeDeletePath) ?>"
            onsubmit="return confirm('<?= $this->escape(
                __('Obrisati stranicu i cijelu njezinu podgranu?'),
            ) ?>')"
        >
            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
            <input type="hidden" name="workspace_id" value="<?= $workspaceId ?>">
            <input type="hidden" name="node_id" value="<?= $nodeId ?>">
            <input type="hidden" name="return_context" value="workspace">
            <input type="hidden" name="return_node_id" value="<?= $returnNodeId ?>">
            <p class="small text-body-secondary mb-0">
                <?= $this->escape(__('Brisanje obuhvaća i sve podređene stavke.')) ?>
            </p>
            <button class="btn btn-danger" type="submit">
                <?= $this->escape(__('Obriši podgranu')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <?= $this->escape(__('Zatvori')) ?>
    </button>
</div>
