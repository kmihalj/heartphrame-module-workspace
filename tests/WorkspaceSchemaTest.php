<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WorkspaceSchemaTest extends TestCase
{
    /**
     * HR: Provjerava da jedina početna migracija na SQLiteu kreira cijelu prijenosnu Workspace shemu.
     * EN: Verifies that the single initial migration creates the complete portable Workspace schema on SQLite.
     */
    public function testInitialMigrationCreatesCompletePortableSchema(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_workspace_schema.php';

        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($database);

        $schema = $database->schema();
        foreach (
            [
                ModuleWorkspace::TABLE_WORKSPACES,
                ModuleWorkspace::TABLE_WORKSPACE_ACL,
                ModuleWorkspace::TABLE_WORKSPACE_NODES,
                ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL,
                ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS,
                ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
                ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES,
            ] as $table
        ) {
            $this->assertTrue($schema->hasTable($table), $table . ' was not created.');
        }

        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACES,
                ['slug', 'visibility', 'owner_user_id', 'is_archived', 'is_deleted'],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
                [
                    'public_node_id',
                    'public_target_type',
                    'public_workspace_id',
                    'public_show_tree',
                    'public_show_display_options',
                    'authenticated_node_id',
                    'authenticated_target_type',
                    'authenticated_workspace_id',
                    'authenticated_show_tree',
                    'authenticated_show_display_options',
                    'allow_user_selection',
                ],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES,
                [
                    'user_id',
                    'node_id',
                    'target_type',
                    'workspace_id',
                    'show_tree',
                    'show_display_options',
                ],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_NODES,
                ['workspace_id', 'parent_id', 'node_type', 'document_key', 'sort_order'],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_ACL,
                ['workspace_id', 'subject_type', 'subject_id', 'can_publish', 'can_manage'],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL,
                ['node_id', 'subject_type', 'subject_id', 'can_view', 'can_publish', 'can_manage'],
            ),
        );
        $this->assertTrue(
            $schema->hasColumns(
                ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS,
                [
                    'node_id',
                    'language_code',
                    'status',
                    'current_version_number',
                    'published_version_number',
                ],
            ),
        );

        $migration->down($database);
        $this->assertFalse($schema->hasTable(ModuleWorkspace::TABLE_WORKSPACES));
    }

    /**
     * HR: Provjerava da zasebna nadogradnja radi na postojećoj instalaciji i da je reverzibilna.
     * EN: Verifies that the standalone upgrade works on an existing installation and is reversible.
     */
    public function testHomepageUpgradeMigrationIsPortableAndReversible(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $database = new Database($config, $helper);
        $migration = require dirname(__DIR__)
        . '/resources/migrations/add_workspace_homepage_preferences.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);

        $migration->up($database);
        $this->assertTrue(
            $database->schema()->hasTable(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS),
        );
        $this->assertTrue(
            $database->schema()->hasTable(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES),
        );

        $migration->down($database);
        $this->assertFalse(
            $database->schema()->hasTable(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS),
        );
    }

    /**
     * HR: Nadogradnja opcija radi nad starim tablicama bez gubitka odabira stranica.
     * EN: The view-options upgrade works on legacy tables without losing page selections.
     */
    public function testHomepageViewOptionsUpgradePreservesLegacyTables(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);
        $database = new Database($config, $helper);
        $schema = $database->schema();
        $schema->create(
            ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
            static function (\AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->bigInteger('public_node_id')->unsigned()->nullable();
                $table->bigInteger('authenticated_node_id')->unsigned()->nullable();
                $table->boolean('allow_user_selection')->default(true);
                $table->timestamps();
            },
        );
        $schema->create(
            ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES,
            static function (\AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->bigInteger('user_id')->unsigned()->unique();
                $table->bigInteger('node_id')->unsigned();
                $table->timestamps();
            },
        );
        $migration = require dirname(__DIR__)
        . '/resources/migrations/add_workspace_homepage_view_options.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);

        $migration->up($database);

        $this->assertTrue($schema->hasColumn(
            ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
            'public_show_display_options',
        ));
        $this->assertTrue($schema->hasColumn(
            ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES,
            'show_display_options',
        ));
        $migration->down($database);
        $this->assertFalse($schema->hasColumn(
            ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS,
            'public_target_type',
        ));
    }
}
