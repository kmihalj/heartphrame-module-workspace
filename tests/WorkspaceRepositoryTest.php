<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspaceRepository::class)]
#[UsesClass(WorkspaceValue::class)]
final class WorkspaceRepositoryTest extends TestCase
{
    /**
     * HR: Trajno brisanje neobjavljene stranice uklanja sve njezine pomoćne
     *     retke, dok izravnu djecu čuva premještanjem na istog roditelja.
     * EN: Permanent deletion of an unpublished page removes all of its
     *     supporting rows while preserving direct children under its parent.
     */
    public function testPermanentlyDeletingUnpublishedNodeRemovesRelationsAndReparentsChildren(): void
    {
        $database = $this->database();
        $repository = new WorkspaceRepository($database);
        $now = '2026-07-18 21:00:00';
        $database->table(ModuleWorkspace::TABLE_WORKSPACES)->insert([
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'slug' => 'test',
            'name' => 'Test',
            'visibility' => 'restricted',
            'owner_user_id' => 1,
            'is_archived' => false,
            'is_deleted' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)->insert([
            'uuid' => '00000000-0000-4000-8000-000000000002',
            'workspace_id' => 1,
            'parent_id' => null,
            'node_type' => 'document',
            'slug' => 'novi-nacrt',
            'title' => 'Novi nacrt',
            'document_key' => 'novi-nacrt',
            'sort_order' => 100,
            'is_homepage' => false,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)->insert([
            'uuid' => '00000000-0000-4000-8000-000000000003',
            'workspace_id' => 1,
            'parent_id' => 1,
            'node_type' => 'document',
            'slug' => 'dijete',
            'title' => 'Dijete',
            'document_key' => 'dijete',
            'sort_order' => 100,
            'is_homepage' => false,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)->insert([
            'node_id' => 1,
            'subject_type' => WorkspaceRepository::SUBJECT_USER,
            'subject_id' => 1,
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
            'can_manage' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)->insert([
            'node_id' => 1,
            'language_code' => 'hr',
            'status' => 'draft',
            'current_version_number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $repository->deleteUnpublishedNodePermanently(1, 1, 1);

        $this->assertNull($repository->findNodeById(1));
        $this->assertNull($repository->nodeWorkflow(1, 'hr'));
        $this->assertSame([], $repository->nodeAclRows(1));
        $child = $repository->findNodeById(2);
        $this->assertIsArray($child);
        $this->assertNull($child['parent_id'] ?? null);
    }

    /**
     * HR: Priprema prijenosnu SQLite bazu s aktualnom inicijalnom Workspace shemom.
     * EN: Prepares a portable SQLite database with the current initial Workspace schema.
     */
    private function database(): Database
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

        return $database;
    }
}
