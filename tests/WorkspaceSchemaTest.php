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
}
