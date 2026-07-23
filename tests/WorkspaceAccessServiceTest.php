<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceWorkflowService;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(WorkspaceAccessService::class)]
#[UsesClass(WorkspaceRepository::class)]
#[UsesClass(WorkspaceConfig::class)]
#[UsesClass(WorkspaceValue::class)]
#[UsesClass(WorkspaceWorkflowService::class)]
final class WorkspaceAccessServiceTest extends TestCase
{
    private Database $database;

    private WorkspaceRepository $repository;

    private AuthnHandlerInterface $authn;

    private WorkspaceAccessService $access;

    /**
     * HR: Priprema prijenosnu SQLite shemu, minimalne Auth subjekte i ACL servis za svaki test.
     * EN: Prepares a portable SQLite schema, minimal Auth subjects, and the ACL service for each test.
     */
    protected function setUp(): void
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
        $this->database = new Database($config, $helper);
        $this->createAuthSchema();

        $migration = require dirname(__DIR__) . '/resources/migrations/initial_workspace_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($this->database);

        $this->repository = new WorkspaceRepository($this->database);
        $this->authn = new class implements AuthnHandlerInterface {
            /**
             * @var mixed[]|null
             */
            private ?array $user = null;

            /**
             * HR: Sprema testnog korisnika bez izvođenja stvarne autentikacije.
             * EN: Stores the test user without performing real authentication.
             *
             * @return mixed[]|null
             */
            public function login(mixed $credentials): ?array
            {
                $this->user = is_array($credentials) ? $credentials : null;
                return $this->user;
            }

            /**
             * HR: Uklanja aktivnog testnog korisnika.
             * EN: Removes the active test user.
             */
            public function logout(): void
            {
                $this->user = null;
            }

            /**
             * HR: Vraća postoji li aktivni testni korisnik.
             * EN: Reports whether an active test user exists.
             */
            public function check(): bool
            {
                return $this->user !== null;
            }

            /**
             * HR: Vraća aktivnog testnog korisnika.
             * EN: Returns the active test user.
             *
             * @return mixed[]|null
             */
            public function user(): ?array
            {
                return $this->user;
            }

            /**
             * HR: Vraća podatke aktivnog testnog korisnika.
             * EN: Returns the active test user's data.
             *
             * @return mixed[]|null
             */
            public function userData(): ?array
            {
                return $this->user;
            }
        };
        $this->access = new WorkspaceAccessService(
            $this->repository,
            $this->authn,
            new WorkspaceConfig($config, dirname(__DIR__)),
            new WorkspaceWorkflowService($this->repository),
        );
    }

    /**
     * HR: Dokazuje da se prava korisnika i grupa zbrajaju, a ograničenje roditelja sužava prava potomka.
     * EN: Proves that user and group grants are combined while a parent restriction narrows descendants.
     */
    public function testUserAndGroupRightsAreCombinedAndNodeRestrictionsAreInherited(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Projekt',
            'slug' => 'projekt',
            'visibility' => 'restricted',
            'owner_user_id' => 1,
        ], 1);
        $workspaceId = (int)$workspace['id'];

        $this->repository->replaceWorkspaceAcl($workspaceId, [
            'user' => [
                2 => ['can_view' => true, 'can_edit' => true],
                4 => ['can_view' => true],
            ],
            'group' => [
                10 => ['can_view' => true, 'can_add' => true],
            ],
        ]);

        $root = $this->repository->saveNode($workspaceId, [
            'title' => 'Korijen',
            'slug' => 'korijen',
            'node_type' => 'document',
            'document_key' => 'root-document',
        ], 1);
        $child = $this->repository->saveNode($workspaceId, [
            'title' => 'Potomak',
            'slug' => 'potomak',
            'node_type' => 'document',
            'document_key' => 'child-document',
            'parent_id' => $root['id'],
        ], 1);

        $this->authn->login(['id' => 2, 'is_admin' => false]);
        $base = $this->access->workspacePermissions($workspace);
        $this->assertTrue($base['can_view']);
        $this->assertTrue($base['can_add']);
        $this->assertTrue($base['can_edit']);
        $this->assertFalse($base['can_delete']);
        $this->assertFalse($base['can_manage']);

        $this->repository->replaceNodeAcl($workspaceId, (int)$root['id'], [
            'user' => [
                2 => ['can_view' => true],
                3 => ['can_view' => true, 'can_edit' => true],
            ],
        ]);

        $restricted = $this->access->nodePermissions($workspace, $child);
        $this->assertTrue($restricted['can_view']);
        $this->assertFalse($restricted['can_add']);
        $this->assertFalse($restricted['can_edit']);
        $this->assertFalse($restricted['can_delete']);
        $this->assertFalse($restricted['can_manage']);
        $this->assertTrue($this->access->canUseDocument('child-document', 'can_view'));
        $this->assertFalse($this->access->canUseDocument('child-document', 'can_edit'));

        $this->authn->login(['id' => 3, 'is_admin' => false]);
        $this->assertFalse($this->access->workspacePermissions($workspace)['can_view']);
        $this->assertFalse($this->access->canUseDocument('child-document', 'can_view'));

        $this->authn->login(['id' => 4, 'is_admin' => false]);
        $this->assertTrue($this->access->workspacePermissions($workspace)['can_view']);
        $this->assertSame([], $this->access->visibleTree($workspace));

        $this->authn->login(['id' => 1, 'is_admin' => false]);
        $this->assertTrue($this->access->nodePermissions($workspace, $child)['can_manage']);
    }

    /**
     * HR: Dokazuje da arhiva ostavlja pregled i upravljanje postavkama, ali zaključava sadržaj i vlasniku i adminu.
     * EN: Proves that archive keeps viewing and settings management while locking content for owner and admin.
     */
    public function testArchivedWorkspaceIsReadOnlyForOwnerAndAdministrator(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Arhiva',
            'slug' => 'arhiva',
            'visibility' => 'restricted',
            'owner_user_id' => 1,
            'is_archived' => true,
        ], 1);
        $node = $this->repository->saveNode((int)$workspace['id'], [
            'title' => 'Dokument',
            'node_type' => 'document',
            'document_key' => 'archived-document',
        ], 1);

        foreach (
            [
                ['id' => 1, 'is_admin' => false],
                ['id' => 2, 'is_admin' => true],
            ] as $user
        ) {
            $this->authn->login($user);
            $permissions = $this->access->nodePermissions($workspace, $node);

            $this->assertTrue($permissions['can_view']);
            $this->assertFalse($permissions['can_add']);
            $this->assertFalse($permissions['can_edit']);
            $this->assertFalse($permissions['can_delete']);
            $this->assertTrue($permissions['can_manage']);
        }
    }

    /**
     * HR: Dokazuje da ugrađena publika Javno ima samo pregled, Svi prijavljeni
     *     može dobiti šira prava te picker pretražuje ograničen Auth imenik.
     * EN: Proves that the built-in Public audience is view-only, All signed-in
     *     users may receive broader rights, and the picker searches a bounded Auth directory.
     */
    public function testBuiltInAudiencesAndDirectorySearch(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Publike',
            'slug' => 'publike',
            'visibility' => 'restricted',
            'owner_user_id' => 1,
        ], 1);
        $workspaceId = (int)$workspace['id'];
        $this->repository->replaceWorkspaceAcl($workspaceId, [
            WorkspaceRepository::SUBJECT_PUBLIC => [
                WorkspaceRepository::BUILT_IN_SUBJECT_ID => ['can_manage' => true],
            ],
            WorkspaceRepository::SUBJECT_AUTHENTICATED => [
                WorkspaceRepository::BUILT_IN_SUBJECT_ID => ['can_add' => true],
            ],
        ]);

        $savedWorkspace = $this->repository->findWorkspaceById($workspaceId);
        $this->assertIsArray($savedWorkspace);
        $this->assertSame('public', $savedWorkspace['visibility'] ?? null);

        $guestPermissions = $this->access->workspacePermissions($savedWorkspace);
        $this->assertTrue($guestPermissions['can_view']);
        $this->assertFalse($guestPermissions['can_add']);
        $this->assertFalse($guestPermissions['can_manage']);

        $this->authn->login(['id' => 3, 'is_admin' => false]);
        $authenticatedPermissions = $this->access->workspacePermissions($savedWorkspace);
        $this->assertTrue($authenticatedPermissions['can_view']);
        $this->assertTrue($authenticatedPermissions['can_add']);
        $this->assertFalse($authenticatedPermissions['can_edit']);

        $subjects = $this->repository->workspaceAclSubjects($workspaceId);
        $public = array_values(array_filter(
            $subjects,
            static fn(array $subject): bool =>
                ($subject['subject_type'] ?? '') === WorkspaceRepository::SUBJECT_PUBLIC,
        ));
        $this->assertCount(1, $public);
        $this->assertTrue((bool)($public[0]['can_view'] ?? false));
        $this->assertFalse((bool)($public[0]['can_add'] ?? true));
        $this->assertTrue((bool)($public[0]['is_read_only'] ?? false));

        $users = $this->repository->searchDirectorySubjects(
            WorkspaceRepository::SUBJECT_USER,
            'Ana',
        );
        $this->assertSame('Ana Horvat', $users[0]['label'] ?? null);

        $groups = $this->repository->searchDirectorySubjects(
            WorkspaceRepository::SUBJECT_GROUP,
            'Jav',
        );
        $this->assertSame(WorkspaceRepository::SUBJECT_PUBLIC, $groups[0]['type'] ?? null);
        $this->assertTrue((bool)($groups[0]['is_read_only'] ?? false));
    }

    /**
     * HR: Provjerava da interni link prihvaća samo lokalnu putanju, a vanjski URL mora koristiti vanjski tip čvora.
     * EN: Verifies that an internal link accepts only a local path while an external URL requires an external node.
     */
    public function testInternalLinkRejectsAnExternalTarget(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Linkovi',
            'owner_user_id' => 1,
        ], 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Interna putanja nije valjana.');
        $this->repository->saveNode((int)$workspace['id'], [
            'title' => 'Pogrešan interni link',
            'node_type' => 'internal_link',
            'target_url' => 'https://example.org',
        ], 1);
    }

    /**
     * HR: Provjerava valjanu lokalnu putanju, jedinstveno vlasništvo dokumenta i zabranu ciklusa u hijerarhiji.
     * EN: Verifies a valid local path, unique document ownership, and cycle prevention in the hierarchy.
     */
    public function testNodeOwnershipAndHierarchyAreValidated(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Stablo',
            'owner_user_id' => 1,
        ], 1);
        $workspaceId = (int)$workspace['id'];
        $internalLink = $this->repository->saveNode($workspaceId, [
            'title' => 'Kalendar',
            'node_type' => 'internal_link',
            'target_url' => '/calendars?mode=month',
        ], 1);
        $this->assertSame('/calendars?mode=month', $internalLink['target_url']);

        $root = $this->repository->saveNode($workspaceId, [
            'title' => 'Korijen',
            'node_type' => 'document',
            'document_key' => 'owned-document',
        ], 1);
        $this->assertSame('korijen', $root['slug']);
        try {
            $this->repository->saveNode($workspaceId, [
                'title' => 'Duplikat',
                'node_type' => 'document',
                'document_key' => 'owned-document',
            ], 1);
            $this->fail('A document key must not belong to two active nodes.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame(
                'HTML dokument već pripada drugoj stranici područja.',
                $runtimeException->getMessage(),
            );
        }

        $child = $this->repository->saveNode($workspaceId, [
            'title' => 'Potomak',
            'node_type' => 'document',
            'document_key' => 'child-owned-document',
            'parent_id' => $root['id'],
        ], 1);
        try {
            $this->repository->saveNode($workspaceId, [
                ...$root,
                'parent_id' => $child['id'],
            ], 1);
            $this->fail('A node must not be moved into its own subtree.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame(
                'Stranicu nije moguće premjestiti u vlastitu podgranu.',
                $runtimeException->getMessage(),
            );
        }
    }

    /**
     * HR: Dokazuje da se cijeli raspored stabla sprema u jednoj transakciji te
     *     da ciklički raspored ne mijenja prethodno valjano stanje.
     * EN: Proves that the complete tree arrangement is saved in one transaction
     *     and that a cyclic arrangement does not change the previous valid state.
     */
    public function testTreeArrangementIsValidatedAndSavedAtomically(): void
    {
        $workspace = $this->repository->saveWorkspace([
            'name' => 'Organizator',
            'owner_user_id' => 1,
        ], 1);
        $workspaceId = (int)$workspace['id'];
        $first = $this->repository->saveNode($workspaceId, [
            'title' => 'Prva',
            'node_type' => 'document',
            'document_key' => 'tree-first',
        ], 1);
        $second = $this->repository->saveNode($workspaceId, [
            'title' => 'Druga',
            'node_type' => 'document',
            'document_key' => 'tree-second',
        ], 1);
        $child = $this->repository->saveNode($workspaceId, [
            'title' => 'Dijete',
            'node_type' => 'document',
            'document_key' => 'tree-child',
            'parent_id' => $first['id'],
        ], 1);

        $this->repository->reorderNodes($workspaceId, [
            ['id' => $second['id'], 'parent_id' => null, 'sort_order' => 10],
            ['id' => $first['id'], 'parent_id' => $second['id'], 'sort_order' => 10],
            ['id' => $child['id'], 'parent_id' => $first['id'], 'sort_order' => 10],
        ], 1);

        $savedFirst = $this->repository->findNodeById((int)$first['id']);
        $savedSecond = $this->repository->findNodeById((int)$second['id']);
        $this->assertSame((int)$second['id'], (int)($savedFirst['parent_id'] ?? 0));
        $this->assertNull($savedSecond['parent_id'] ?? null);

        try {
            $this->repository->reorderNodes($workspaceId, [
                ['id' => $second['id'], 'parent_id' => $child['id'], 'sort_order' => 10],
                ['id' => $first['id'], 'parent_id' => $second['id'], 'sort_order' => 10],
                ['id' => $child['id'], 'parent_id' => $first['id'], 'sort_order' => 10],
            ], 1);
            $this->fail('A cyclic tree arrangement must be rejected.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame(
                'Stranicu nije moguće premjestiti u vlastitu podgranu.',
                $runtimeException->getMessage(),
            );
        }

        $unchangedSecond = $this->repository->findNodeById((int)$second['id']);
        $this->assertNull($unchangedSecond['parent_id'] ?? null);
    }

    /**
     * HR: Kreira samo Auth stupce koje Workspace repository koristi za provjeru korisnika, grupa i članstva.
     * EN: Creates only the Auth columns used by the Workspace repository for users, groups, and membership.
     */
    private function createAuthSchema(): void
    {
        $schema = $this->database->schema();
        $schema->create('auth_users', static function (Blueprint $table): void {
            $table->id();
            $table->string('login_identifier');
            $table->boolean('is_active')->default(true);
        });
        $schema->create('auth_groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('group_name');
            $table->boolean('is_enabled')->default(true);
        });
        $schema->create('auth_user_groups', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->bigInteger('group_id')->unsigned()->index();
        });
        $schema->create('auth_user_attribute_values', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->string('field_key');
            $table->text('value_text')->nullable();
        });

        foreach ([1, 2, 3, 4] as $userId) {
            $this->database->table('auth_users')->insert([
                'id' => $userId,
                'login_identifier' => 'user' . $userId,
                'is_active' => true,
            ]);
        }

        $this->database->table('auth_groups')->insert([
            'id' => 10,
            'group_name' => 'Urednici',
            'is_enabled' => true,
        ]);
        $this->database->table('auth_user_groups')->insert([
            'user_id' => 2,
            'group_id' => 10,
        ]);
        $this->database->table('auth_user_attribute_values')->insert([
            'user_id' => 2,
            'field_key' => 'display_name',
            'value_text' => 'Ana Horvat',
        ]);
    }
}
