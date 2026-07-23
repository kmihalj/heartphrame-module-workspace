<?php

declare(strict_types=1);

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var string $message
 * @var string $indexPath
 */
?>
<section class="border rounded p-4" aria-labelledby="workspace-error-title">
    <h1 id="workspace-error-title" class="h3 mb-2"><?= $this->escape($title) ?></h1>
    <p class="text-body-secondary mb-3"><?= $this->escape($message) ?></p>
    <a class="btn btn-secondary" href="<?= $this->escape($indexPath) ?>">
        <?= $this->escape(__('Natrag na područja')) ?>
    </a>
</section>
