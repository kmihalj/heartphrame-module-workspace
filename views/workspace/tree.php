<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

/**
 * @var \HeartPhrame\View\View $this
 * @var list<array<string, mixed>> $nodes
 * @var int|null $activeNodeId
 * @var int $level
 */
$level = max(1, $level ?? 1);
?>
<?php if ($nodes !== []) : ?>
    <?php foreach ($nodes as $treeNode) : ?>
        <?php
        $treeNodeId = WorkspaceValue::int($treeNode['id'] ?? 0);
        $children = WorkspaceValue::rows($treeNode['children'] ?? null);
        $isActive = $treeNodeId > 0 && $treeNodeId === ($activeNodeId ?? null);
        $workflowLabel = WorkspaceValue::string($treeNode['workflow_label'] ?? '');
        $workflowStatus = WorkspaceValue::string($treeNode['workflow_status'] ?? '');
        ?>
        <a
            class="list-group-item list-group-item-action workspace-tree-link
                workspace-tree-link-level-<?= $this->escape((string)$level) ?><?= $isActive ? ' active' : '' ?>"
            href="<?= $this->escape(WorkspaceValue::string($treeNode['href'] ?? '#')) ?>"
        <?= WorkspaceValue::string($treeNode['node_type'] ?? '') === 'external_link'
        ? 'target="_blank" rel="noopener noreferrer"'
        : '' ?>
        >
            <span class="workspace-tree-link-title">
        <?= $this->escape(WorkspaceValue::string($treeNode['title'] ?? '')) ?>
            </span>
        <?php if ($workflowLabel !== '') : ?>
            <span
                class="badge rounded-pill workspace-tree-status
            <?= $workflowStatus === 'in_review' ? 'text-bg-warning' : 'text-bg-info' ?>"
                title="<?= $this->escape($workflowLabel) ?>"
            >
            <?= $this->escape($workflowLabel) ?>
            </span>
        <?php endif; ?>
        </a>
        <?php if ($children !== []) : ?>
            <?= $this->forModulePartial(
                'aaieduhr/heartphrame-module-workspace',
                'workspace/tree',
                ['nodes' => $children, 'activeNodeId' => $activeNodeId, 'level' => $level + 1],
            ) ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
