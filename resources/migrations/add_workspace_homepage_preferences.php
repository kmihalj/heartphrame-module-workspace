<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Dodaje postavke javne i prijavljene naslovnice te osobni odabir
     * korisnika postojećoj Workspace instalaciji.
     * EN: Adds public and authenticated homepage settings plus personal user
     * selection to an existing Workspace installation.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)) {
            $schema->create(
                ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
                static function (Blueprint $table): void {
                    $table->id();
                    $table->bigInteger('public_node_id')->unsigned()->nullable()->index();
                    $table->bigInteger('authenticated_node_id')->unsigned()->nullable()->index();
                    $table->boolean('allow_user_selection')->default(true)->index();
                    $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                    $table->timestamps();
                },
            );
        }

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES)) {
            $schema->create(
                ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES,
                static function (Blueprint $table): void {
                    $table->id();
                    $table->bigInteger('user_id')->unsigned()->unique();
                    $table->bigInteger('node_id')->unsigned()->index();
                    $table->timestamps();
                },
            );
        }
    }

    /**
     * HR: Uklanja samo tablice dodane ovom nadogradnjom.
     * EN: Drops only the tables introduced by this upgrade.
     */
    public function down(Database $db): void
    {
        $schema = $db->schema();
        $schema->dropIfExists(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES);
        $schema->dropIfExists(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS);
    }
};
