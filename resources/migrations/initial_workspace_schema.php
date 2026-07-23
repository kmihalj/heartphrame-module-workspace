<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Kreira početnu prenosivu shemu za područja, članstva, stablo sadržaja
     * i ograničenja koja se nasljeđuju kroz roditeljske čvorove.
     *
     * EN: Creates the initial portable schema for workspaces, memberships, the
     * content tree, and restrictions inherited through parent nodes.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACES)) {
            $schema->create(ModuleWorkspace::TABLE_WORKSPACES, static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('slug', 128)->unique();
                $table->string('name', 190)->index();
                $table->text('description')->nullable();
                $table->string('visibility', 32)->default('restricted')->index();
                $table->bigInteger('owner_user_id')->unsigned()->index();
                $table->boolean('is_archived')->default(false)->index();
                $table->boolean('is_deleted')->default(false)->index();
                $table->bigInteger('created_by_user_id')->unsigned()->nullable()->index();
                $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                $table->bigInteger('deleted_by_user_id')->unsigned()->nullable()->index();
                $table->timestamp('deleted_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_ACL)) {
            $schema->create(ModuleWorkspace::TABLE_WORKSPACE_ACL, static function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('workspace_id')->unsigned()->index();
                $table->string('subject_type', 16)->index();
                $table->bigInteger('subject_id')->unsigned()->index();
                $table->boolean('can_view')->default(true)->index();
                $table->boolean('can_add')->default(false)->index();
                $table->boolean('can_edit')->default(false)->index();
                $table->boolean('can_publish')->default(false)->index();
                $table->boolean('can_delete')->default(false)->index();
                $table->boolean('can_manage')->default(false)->index();
                $table->timestamps();

                $table->unique(
                    ['workspace_id', 'subject_type', 'subject_id'],
                    'workspace_acl_subject_unique',
                );
            });
        }

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODES)) {
            $schema->create(ModuleWorkspace::TABLE_WORKSPACE_NODES, static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->bigInteger('workspace_id')->unsigned()->index();
                $table->bigInteger('parent_id')->unsigned()->nullable()->index();
                $table->string('node_type', 32)->default('document')->index();
                $table->string('slug', 128)->index();
                $table->string('title', 255);
                $table->string('document_key', 190)->nullable()->index();
                $table->string('route_name', 190)->nullable()->index();
                $table->string('target_url', 1024)->nullable();
                $table->integer('sort_order')->default(100)->index();
                $table->boolean('is_homepage')->default(false)->index();
                $table->boolean('is_enabled')->default(true)->index();
                $table->bigInteger('created_by_user_id')->unsigned()->nullable()->index();
                $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                $table->timestamps();

                $table->unique(['workspace_id', 'slug'], 'workspace_node_slug_unique');
                $table->index(
                    ['workspace_id', 'parent_id', 'sort_order'],
                    'workspace_node_tree_order_idx',
                );
            });
        }

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)) {
            $schema->create(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL, static function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('node_id')->unsigned()->index();
                $table->string('subject_type', 16)->index();
                $table->bigInteger('subject_id')->unsigned()->index();
                $table->boolean('can_view')->default(true)->index();
                $table->boolean('can_add')->default(false)->index();
                $table->boolean('can_edit')->default(false)->index();
                $table->boolean('can_publish')->default(false)->index();
                $table->boolean('can_delete')->default(false)->index();
                $table->boolean('can_manage')->default(false)->index();
                $table->timestamps();

                $table->unique(
                    ['node_id', 'subject_type', 'subject_id'],
                    'workspace_node_acl_subject_unique',
                );
            });
        }

        if (!$schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)) {
            $schema->create(
                ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS,
                static function (Blueprint $table): void {
                    $table->id();
                    $table->bigInteger('node_id')->unsigned()->index();
                    $table->string('language_code', 16)->index();
                    $table->string('status', 32)->default('draft')->index();
                    $table->integer('current_version_number')->unsigned()->nullable()->index();
                    $table->integer('published_version_number')->unsigned()->nullable()->index();
                    $table->bigInteger('submitted_by_user_id')->unsigned()->nullable()->index();
                    $table->timestamp('submitted_at')->nullable()->index();
                    $table->bigInteger('published_by_user_id')->unsigned()->nullable()->index();
                    $table->timestamp('published_at')->nullable()->index();
                    $table->bigInteger('archived_by_user_id')->unsigned()->nullable()->index();
                    $table->timestamp('archived_at')->nullable()->index();
                    $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                    $table->timestamps();

                    $table->unique(
                        ['node_id', 'language_code'],
                        'workspace_node_workflow_language_unique',
                    );
                },
            );
        }
    }

    /**
     * HR: Briše Workspace tablice obrnutim redoslijedom bez diranja auth ili
     * editor podataka koji pripadaju drugim modulima.
     *
     * EN: Drops Workspace tables in reverse order without touching auth or
     * editor data owned by other modules.
     */
    public function down(Database $db): void
    {
        $schema = $db->schema();

        foreach (
            [
                ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS,
                ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL,
                ModuleWorkspace::TABLE_WORKSPACE_NODES,
                ModuleWorkspace::TABLE_WORKSPACE_ACL,
                ModuleWorkspace::TABLE_WORKSPACES,
            ] as $table
        ) {
            $schema->dropIfExists($table);
        }
    }
};
