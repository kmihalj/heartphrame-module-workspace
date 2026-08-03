<?php

declare(strict_types=1);

/**
 * HR: Osobna Workspace naslovnica prikazana kroz modularni Auth profil.
 * EN: Personal Workspace homepage displayed through the modular Auth profile.
 *
 * @var \HeartPhrame\View\View $this
 * @var int $selectedNodeId
 * @var bool $selectionUnavailable
 * @var list<array{name:string,options:list<array{id:int,title:string}>}> $optionGroups
 * @var string $savePath
 */
?>
<div class="card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-2"><?= $this->escape(__('Osobna naslovnica')) ?></h2>
        <p class="text-body-secondary mb-3">
            <?= $this->escape(
                __('Odaberite objavljenu stranicu područja koja će se otvoriti nakon dolaska na naslovnicu.'),
            ) ?>
        </p>

        <?php if ($selectionUnavailable) : ?>
            <div class="alert alert-warning" role="alert">
            <?= $this->escape(
                __('Prethodno odabrana stranica više nije dostupna pa se koristi zadana naslovnica.'),
            ) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= $this->escape($savePath) ?>">
            <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
            <label class="form-label" for="workspace-personal-homepage">
                <?= $this->escape(__('Moja naslovnica')) ?>
            </label>
            <select
                id="workspace-personal-homepage"
                class="form-select"
                name="node_id"
            >
                <option value="0"><?= $this->escape(__('Koristi zadanu naslovnicu')) ?></option>
                <?php foreach ($optionGroups as $group) : ?>
                    <optgroup label="<?= $this->escape($group['name']) ?>">
                    <?php foreach ($group['options'] as $option) : ?>
                            <option
                                value="<?= $option['id'] ?>"
                        <?= $option['id'] === $selectedNodeId ? 'selected' : '' ?>
                            >
                        <?= $this->escape($option['title']) ?>
                            </option>
                    <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary mt-3">
                <?= $this->escape(__('Spremi osobnu naslovnicu')) ?>
            </button>
        </form>
    </div>
</div>
