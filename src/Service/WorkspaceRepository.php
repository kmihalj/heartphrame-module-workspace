<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function is_array;
use function is_numeric;
use function is_scalar;
use function mb_strtolower;
use function preg_replace;
use function sort;
use function str_contains;
use function str_starts_with;
use function strcasecmp;
use function strtolower;
use function trim;
use function usort;

final readonly class WorkspaceRepository
{
    public const SUBJECT_USER = 'user';

    public const SUBJECT_GROUP = 'group';

    public const SUBJECT_PUBLIC = 'public';

    public const SUBJECT_AUTHENTICATED = 'authenticated';

    public const BUILT_IN_SUBJECT_ID = 1;

    private const DIRECTORY_RESULT_LIMIT = 20;

    private const AUTH_USERS_TABLE = 'auth_users';

    private const AUTH_GROUPS_TABLE = 'auth_groups';

    private const AUTH_USER_GROUPS_TABLE = 'auth_user_groups';

    private const AUTH_ATTRIBUTE_VALUES_TABLE = 'auth_user_attribute_values';

    /**
     * HR: Prima ORM bazu i postaje jedino mjesto koje izravno čita Workspace tablice.
     * EN: Receives the ORM database and becomes the only direct reader of Workspace tables.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * HR: Provjerava je li inicijalna Workspace migracija primijenjena.
     * EN: Checks whether the initial Workspace migration has been applied.
     */
    public function tablesReady(): bool
    {
        $schema = $this->database->schema();

        return $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACES)
        && $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_ACL)
        && $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODES)
        && $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)
        && $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS);
    }

    /**
     * HR: Vraća sva aktivna područja za kasnije filtriranje kroz ACL servis.
     * EN: Returns every active workspace for later filtering by the ACL service.
     *
     * @return list<array<string, mixed>>
     */
    public function activeWorkspaces(): array
    {
        $this->assertTablesReady();

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
                ->where('is_deleted', '=', false)
                ->orderBy('name', 'ASC')
                ->get(),
        );
    }

    /**
     * HR: Vraća sva područja administratoru, uključujući arhivirana.
     * EN: Returns all non-deleted workspaces to administrators, including archived ones.
     *
     * @return list<array<string, mixed>>
     */
    public function allWorkspaces(): array
    {
        return $this->activeWorkspaces();
    }

    /**
     * HR: Vraća soft-obrisana područja za administratorsko vraćanje.
     * EN: Returns soft-deleted workspaces for administrator restoration.
     *
     * @return list<array<string, mixed>>
     */
    public function deletedWorkspaces(): array
    {
        $this->assertTablesReady();

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
                ->where('is_deleted', '=', true)
                ->orderBy('deleted_at', 'DESC')
                ->get(),
        );
    }

    /**
     * HR: Učitava aktivno područje po javnom slugu.
     * EN: Loads an active workspace by its public slug.
     *
     * @return array<string, mixed>|null
     */
    public function findWorkspaceBySlug(string $slug): ?array
    {
        $this->assertTablesReady();
        $slug = $this->slug($slug, 'workspace');
        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
            ->where('slug', '=', $slug)
            ->where('is_deleted', '=', false)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Učitava područje po internom ID-u, po potrebi i kada je obrisano.
     * EN: Loads a workspace by internal ID, optionally including deleted rows.
     *
     * @return array<string, mixed>|null
     */
    public function findWorkspaceById(int $workspaceId, bool $includeDeleted = false): ?array
    {
        $this->assertTablesReady();
        $query = $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
            ->where('id', '=', $workspaceId);
        if (!$includeDeleted) {
            $query->where('is_deleted', '=', false);
        }

        $row = $query->first();

        return $this->row($row);
    }

    /**
     * HR: Kreira ili ažurira područje nakon što je business sloj provjerio ovlasti.
     * EN: Creates or updates a workspace after the business layer has checked permissions.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveWorkspace(array $data, int $actorUserId): array
    {
        $this->assertTablesReady();
        $workspaceId = $this->intValue($data['id'] ?? 0);
        $existing = $workspaceId > 0 ? $this->findWorkspaceById($workspaceId) : null;
        $name = $this->stringValue($data['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException(__('Naziv područja je obavezan.'));
        }

        $slug = $this->uniqueWorkspaceSlug(
            $this->slug($data['slug'] ?? $name, 'workspace'),
            $workspaceId,
        );
        $visibility = $this->visibility(
            $data['visibility'] ?? (is_array($existing) ? $existing['visibility'] ?? 'restricted' : 'restricted'),
        );
        $ownerUserId = $this->intValue($data['owner_user_id'] ?? $actorUserId);
        if ($ownerUserId <= 0 || !$this->userExists($ownerUserId)) {
            throw new RuntimeException(__('Vlasnik područja nije valjan.'));
        }

        $now = date('Y-m-d H:i:s');
        $values = [
            'slug' => $slug,
            'name' => $name,
            'description' => $this->stringValue($data['description'] ?? ''),
            'visibility' => $visibility,
            'owner_user_id' => $ownerUserId,
            'is_archived' => $this->boolValue($data['is_archived'] ?? false),
            'updated_by_user_id' => $actorUserId,
            'updated_at' => $now,
        ];

        $isNew = $workspaceId <= 0;
        if (!$isNew) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
                ->where('id', '=', $workspaceId)
                ->where('is_deleted', '=', false)
                ->update($values);
        } else {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)->insert([
                'uuid' => $this->uuid(),
                'is_deleted' => false,
                'created_by_user_id' => $actorUserId,
                'created_at' => $now,
                ...$values,
            ]);
            $workspaceId = (int)$this->database->lastInsertId();
        }

        if ($isNew) {
            $this->insertBuiltInVisibilityAcl($workspaceId, $visibility, $now);
        }

        $workspace = $this->findWorkspaceById($workspaceId);
        if (!is_array($workspace)) {
            throw new RuntimeException(__('Spremljeno područje nije moguće učitati.'));
        }

        return $workspace;
    }

    /**
     * HR: Soft-briše područje, dok stablo i povezni dokumenti ostaju dostupni za vraćanje.
     * EN: Soft-deletes a workspace while preserving its tree and linked documents for restoration.
     */
    public function softDeleteWorkspace(int $workspaceId, int $actorUserId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
            ->where('id', '=', $workspaceId)
            ->where('is_deleted', '=', false)
            ->update([
                'is_deleted' => true,
                'deleted_by_user_id' => $actorUserId,
                'deleted_at' => $now,
                'updated_by_user_id' => $actorUserId,
                'updated_at' => $now,
            ]);
    }

    /**
     * HR: Vraća soft-obrisano područje pod slobodnim slugom.
     * EN: Restores a soft-deleted workspace under an available slug.
     *
     * @return array<string, mixed>
     */
    public function restoreWorkspace(int $workspaceId, string $preferredSlug, int $actorUserId): array
    {
        $workspace = $this->findWorkspaceById($workspaceId, true);
        if (!is_array($workspace) || !(bool)($workspace['is_deleted'] ?? false)) {
            throw new RuntimeException(__('Obrisano područje nije pronađeno.'));
        }

        $slug = $this->uniqueWorkspaceSlug(
            $this->slug($preferredSlug !== '' ? $preferredSlug : $workspace['slug'] ?? '', 'workspace'),
            $workspaceId,
        );
        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
            ->where('id', '=', $workspaceId)
            ->update([
                'slug' => $slug,
                'is_deleted' => false,
                'deleted_by_user_id' => null,
                'deleted_at' => null,
                'updated_by_user_id' => $actorUserId,
                'updated_at' => $now,
            ]);

        $restored = $this->findWorkspaceById($workspaceId);
        if (!is_array($restored)) {
            throw new RuntimeException(__('Vraćeno područje nije moguće učitati.'));
        }

        return $restored;
    }

    /**
     * HR: Vraća ACL retke jednog područja.
     * EN: Returns ACL rows for one workspace.
     *
     * @return list<array<string, mixed>>
     */
    public function workspaceAclRows(int $workspaceId): array
    {
        $this->assertTablesReady();

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_ACL)
                ->where('workspace_id', '=', $workspaceId)
                ->orderBy('subject_type', 'ASC')
                ->orderBy('subject_id', 'ASC')
                ->get(),
        );
    }

    /**
     * HR: Vraća samo ACL subjekte koji su dodani području, s prikaznim nazivima
     *     dohvaćenima grupno. Stara visibility vrijednost se prikazuje kao
     *     ugrađeni subjekt dok se ACL prvi put ne spremi.
     * EN: Returns only ACL subjects assigned to a Workspace, with display labels
     *     fetched in batches. A legacy visibility value is represented as a
     *     built-in subject until the ACL is saved for the first time.
     *
     * @return list<array<string, mixed>>
     */
    public function workspaceAclSubjects(int $workspaceId): array
    {
        $rows = $this->workspaceAclRows($workspaceId);
        $hasPublic = false;
        $hasAuthenticated = false;
        foreach ($rows as $row) {
            $type = $this->stringValue($row['subject_type'] ?? '');
            $hasPublic = $hasPublic || $type === self::SUBJECT_PUBLIC;
            $hasAuthenticated = $hasAuthenticated || $type === self::SUBJECT_AUTHENTICATED;
        }

        if (!$hasPublic && !$hasAuthenticated) {
            $workspace = $this->findWorkspaceById($workspaceId);
            $visibility = $this->visibility($workspace['visibility'] ?? 'restricted');
            if ($visibility === self::SUBJECT_PUBLIC || $visibility === self::SUBJECT_AUTHENTICATED) {
                $rows[] = [
                    'workspace_id' => $workspaceId,
                    'subject_type' => $visibility,
                    'subject_id' => self::BUILT_IN_SUBJECT_ID,
                    'can_view' => true,
                    'can_add' => false,
                    'can_edit' => false,
                    'can_publish' => false,
                    'can_delete' => false,
                    'can_manage' => false,
                ];
            }
        }

        $userIds = [];
        $groupIds = [];
        foreach ($rows as $row) {
            $type = $this->stringValue($row['subject_type'] ?? '');
            $id = $this->intValue($row['subject_id'] ?? 0);
            if ($type === self::SUBJECT_USER && $id > 0) {
                $userIds[] = $id;
            } elseif ($type === self::SUBJECT_GROUP && $id > 0) {
                $groupIds[] = $id;
            }
        }

        $users = $this->usersByIds($userIds);
        $groups = $this->groupsByIds($groupIds);
        $subjects = [];
        foreach ($rows as $row) {
            $type = $this->stringValue($row['subject_type'] ?? '');
            $id = $this->intValue($row['subject_id'] ?? 0);
            $subject = $row;
            $subject['category'] = $type === self::SUBJECT_USER ? self::SUBJECT_USER : self::SUBJECT_GROUP;
            $subject['is_builtin'] = in_array(
                $type,
                [self::SUBJECT_PUBLIC, self::SUBJECT_AUTHENTICATED],
                true,
            );
            $subject['is_read_only'] = $type === self::SUBJECT_PUBLIC;

            if ($type === self::SUBJECT_USER && isset($users[$id])) {
                $subject['label'] = $this->stringValue($users[$id]['label'] ?? '');
            } elseif ($type === self::SUBJECT_GROUP && isset($groups[$id])) {
                $subject['label'] = $this->stringValue($groups[$id]['group_name'] ?? '');
            } elseif ($type === self::SUBJECT_PUBLIC && $id === self::BUILT_IN_SUBJECT_ID) {
                $subject['label'] = __('Javno');
            } elseif ($type === self::SUBJECT_AUTHENTICATED && $id === self::BUILT_IN_SUBJECT_ID) {
                $subject['label'] = __('Svi prijavljeni');
            } else {
                continue;
            }

            $subjects[] = $subject;
        }

        usort(
            $subjects,
            static fn(array $left, array $right): int => strcasecmp(
                (string)($left['label'] ?? ''),
                (string)($right['label'] ?? ''),
            ),
        );

        return $subjects;
    }

    /**
     * HR: Pretražuje aktivne korisnike ili grupe u malom, ograničenom skupu
     *     rezultata za asinkroni ACL i owner picker.
     * EN: Searches active users or groups in a small, bounded result set for
     *     asynchronous ACL and owner pickers.
     *
     * @return list<array<string, mixed>>
     */
    public function searchDirectorySubjects(string $category, string $search): array
    {
        $search = trim($search);
        if ($category === self::SUBJECT_USER) {
            return $this->searchUsers($search, self::DIRECTORY_RESULT_LIMIT);
        }

        if ($category !== self::SUBJECT_GROUP) {
            return [];
        }

        return $this->searchGroups($search, self::DIRECTORY_RESULT_LIMIT);
    }

    /**
     * HR: Vraća jedan aktivni korisnički subjekt za početnu vrijednost owner pickera.
     * EN: Returns one active user subject for the initial owner-picker value.
     *
     * @return array<string, mixed>|null
     */
    public function userSubject(int $userId): ?array
    {
        $users = $this->usersByIds([$userId]);

        return $users[$userId] ?? null;
    }

    /**
     * HR: Zamjenjuje ACL područja stvarno odabranim korisnicima, grupama i
     *     ugrađenim publikama iz administratorske forme.
     * EN: Replaces Workspace ACL entries with the users, groups, and built-in
     *     audiences actually selected in the administration form.
     *
     * @param array<string, mixed> $acl
     */
    public function replaceWorkspaceAcl(int $workspaceId, array $acl): void
    {
        $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_ACL)
            ->where('workspace_id', '=', $workspaceId)
            ->delete();

        $now = date('Y-m-d H:i:s');
        $savedBuiltIns = [];
        foreach (
            [
                self::SUBJECT_USER,
                self::SUBJECT_GROUP,
                self::SUBJECT_PUBLIC,
                self::SUBJECT_AUTHENTICATED,
            ] as $subjectType
        ) {
            $subjects = is_array($acl[$subjectType] ?? null) ? $acl[$subjectType] : [];
            foreach ($subjects as $subjectId => $permissions) {
                $subjectId = $this->intValue($subjectId);
                if ($subjectId <= 0) {
                    continue;
                }

                if (!is_array($permissions)) {
                    continue;
                }

                if (!$this->subjectExists($subjectType, $subjectId)) {
                    continue;
                }

                $normalized = $this->permissionValues(WorkspaceValue::stringKeyArray($permissions));
                if ($subjectType === self::SUBJECT_PUBLIC) {
                    $normalized = [
                        'can_view' => $normalized['can_view'],
                        'can_add' => false,
                        'can_edit' => false,
                        'can_publish' => false,
                        'can_delete' => false,
                        'can_manage' => false,
                    ];
                }

                if (!$this->hasAnyPermission($normalized)) {
                    continue;
                }

                $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_ACL)->insert([
                    'workspace_id' => $workspaceId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    ...$normalized,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($subjectType === self::SUBJECT_PUBLIC || $subjectType === self::SUBJECT_AUTHENTICATED) {
                    $savedBuiltIns[$subjectType] = $normalized;
                }
            }
        }

        $visibility = isset($savedBuiltIns[self::SUBJECT_PUBLIC])
        ? self::SUBJECT_PUBLIC
        : (isset($savedBuiltIns[self::SUBJECT_AUTHENTICATED])
            ? self::SUBJECT_AUTHENTICATED
            : 'restricted');
        $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
            ->where('id', '=', $workspaceId)
            ->update([
                'visibility' => $visibility,
                'updated_at' => $now,
            ]);
    }

    /**
     * HR: Vraća sve aktivne čvorove područja u stabilnom hijerarhijskom redoslijedu.
     * EN: Returns every active workspace node in stable hierarchical order.
     *
     * @return list<array<string, mixed>>
     */
    public function nodesForWorkspace(int $workspaceId): array
    {
        $this->assertTablesReady();

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                ->where('workspace_id', '=', $workspaceId)
                ->where('is_enabled', '=', true)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('title', 'ASC')
                ->get(),
        );
    }

    /**
     * HR: Učitava aktivni čvor po internom ID-u.
     * EN: Loads an active node by internal ID.
     *
     * @return array<string, mixed>|null
     */
    public function findNodeById(int $nodeId): ?array
    {
        $this->assertTablesReady();
        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
            ->where('id', '=', $nodeId)
            ->where('is_enabled', '=', true)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Učitava aktivni čvor po području i URL slugu.
     * EN: Loads an active node by workspace and URL slug.
     *
     * @return array<string, mixed>|null
     */
    public function findNodeBySlug(int $workspaceId, string $slug): ?array
    {
        $this->assertTablesReady();
        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
            ->where('workspace_id', '=', $workspaceId)
            ->where('slug', '=', $this->slug($slug, 'page'))
            ->where('is_enabled', '=', true)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Pronalazi aktivni Workspace čvor povezan s editor dokumentom.
     * EN: Finds the active Workspace node linked to an editor document.
     *
     * @return array<string, mixed>|null
     */
    public function findNodeByDocumentKey(string $documentKey): ?array
    {
        $this->assertTablesReady();
        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
            ->where('document_key', '=', trim($documentKey))
            ->where('node_type', '=', 'document')
            ->where('is_enabled', '=', true)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Učitava workflow stanje jednog jezika stranice ili vraća null dok
     *     stranica još nije ušla u kontrolirani proces objave.
     * EN: Loads one page-language workflow state or returns null until the page
     *     has entered the managed publishing process.
     *
     * @return array<string, mixed>|null
     */
    public function nodeWorkflow(int $nodeId, string $language): ?array
    {
        $this->assertTablesReady();
        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)
            ->where('node_id', '=', $nodeId)
            ->where('language_code', '=', $this->language($language))
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Grupno učitava workflow stanja zadanih stranica za jedan jezik kako
     *     velika stabla ne bi izvodila zaseban upit za svaki čvor.
     * EN: Loads workflow states for the requested pages and one locale in a
     *     single batch so large trees do not issue one query per node.
     *
     * @param list<int> $nodeIds
     * @return array<int, array<string, mixed>>
     */
    public function nodeWorkflowsForNodes(array $nodeIds, string $language): array
    {
        $this->assertTablesReady();
        $nodeIds = array_values(array_unique(array_filter(
            $nodeIds,
            static fn(int $nodeId): bool => $nodeId > 0,
        )));
        if ($nodeIds === []) {
            return [];
        }

        $indexed = [];
        foreach (
            $this->rows(
                $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)
                    ->where('language_code', '=', $this->language($language))
                    ->whereIn('node_id', $nodeIds)
                    ->get(),
            ) as $workflow
        ) {
            $nodeId = $this->intValue($workflow['node_id'] ?? 0);
            if ($nodeId > 0) {
                $indexed[$nodeId] = $workflow;
            }
        }

        return $indexed;
    }

    /**
     * HR: Sprema aktualnu workflow snimku uz jedinstven zapis po stranici i
     *     jeziku. Poslovni servis prije poziva provjerava dopušteni prijelaz.
     * EN: Stores the current workflow snapshot in one unique row per page and
     *     language. The business service validates the transition first.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function saveNodeWorkflow(
        int $nodeId,
        string $language,
        array $values,
        int $actorUserId,
    ): array {
        $this->assertTablesReady();
        $language = $this->language($language);
        $existing = $this->nodeWorkflow($nodeId, $language);
        $now = date('Y-m-d H:i:s');
        $payload = [
            'status' => $this->stringValue($values['status'] ?? $existing['status'] ?? 'draft'),
            'current_version_number' => $this->nullablePositiveInt(
                $this->workflowValue($values, $existing, 'current_version_number'),
            ),
            'published_version_number' => $this->nullablePositiveInt(
                $this->workflowValue($values, $existing, 'published_version_number'),
            ),
            'submitted_by_user_id' => $this->nullablePositiveInt(
                $this->workflowValue($values, $existing, 'submitted_by_user_id'),
            ),
            'submitted_at' => $this->workflowValue($values, $existing, 'submitted_at'),
            'published_by_user_id' => $this->nullablePositiveInt(
                $this->workflowValue($values, $existing, 'published_by_user_id'),
            ),
            'published_at' => $this->workflowValue($values, $existing, 'published_at'),
            'archived_by_user_id' => $this->nullablePositiveInt(
                $this->workflowValue($values, $existing, 'archived_by_user_id'),
            ),
            'archived_at' => $this->workflowValue($values, $existing, 'archived_at'),
            'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => $now,
        ];

        if (is_array($existing)) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)
                ->where('id', '=', $this->intValue($existing['id'] ?? 0))
                ->update($payload);
        } else {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)->insert([
                'node_id' => $nodeId,
                'language_code' => $language,
                'created_at' => $now,
                ...$payload,
            ]);
        }

        $saved = $this->nodeWorkflow($nodeId, $language);
        if (!is_array($saved)) {
            throw new RuntimeException(__('Workflow stranice nije moguće spremiti.'));
        }

        return $saved;
    }

    /**
     * HR: Kreira ili mijenja čvor stabla nakon centralne provjere ovlasti.
     * EN: Creates or updates a tree node after centralized permission checks.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveNode(int $workspaceId, array $data, int $actorUserId): array
    {
        $this->assertTablesReady();
        $nodeId = $this->intValue($data['id'] ?? 0);
        $title = $this->stringValue($data['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException(__('Naslov čvora je obavezan.'));
        }

        $nodeType = $this->nodeType($data['node_type'] ?? 'document');
        $slugInput = $this->stringValue($data['slug'] ?? '');
        $slug = $this->uniqueNodeSlug(
            $workspaceId,
            $this->slug($slugInput !== '' ? $slugInput : $title, 'page'),
            $nodeId,
        );
        $parentId = $this->nullablePositiveInt($data['parent_id'] ?? null);
        if ($parentId !== null) {
            $parent = $this->findNodeById($parentId);
            if (!is_array($parent) || $this->intValue($parent['workspace_id'] ?? 0) !== $workspaceId) {
                throw new RuntimeException(__('Roditeljska stranica nije valjana.'));
            }

            if ($nodeId > 0 && $this->wouldCreateCycle($nodeId, $parentId)) {
                throw new RuntimeException(__('Stranicu nije moguće premjestiti u vlastitu podgranu.'));
            }
        }

        $documentKey = $nodeType === 'document'
        ? $this->stringValue($data['document_key'] ?? '')
        : null;
        $routeName = $nodeType === 'internal_link'
        ? $this->stringValue($data['route_name'] ?? '')
        : null;
        $targetUrl = in_array($nodeType, ['internal_link', 'external_link'], true)
        ? $this->stringValue($data['target_url'] ?? '')
        : null;
        if ($nodeType === 'document' && $documentKey === '') {
            throw new RuntimeException(__('HTML dokument nije odabran.'));
        }

        if ($nodeType === 'document') {
            $documentNode = $this->findNodeByDocumentKey((string)$documentKey);
            if (
                is_array($documentNode)
                && $this->intValue($documentNode['id'] ?? 0) !== $nodeId
            ) {
                throw new RuntimeException(__('HTML dokument već pripada drugoj stranici područja.'));
            }
        }

        if ($nodeType === 'external_link' && !$this->validExternalUrl((string)$targetUrl)) {
            throw new RuntimeException(__('Vanjski URL nije valjan.'));
        }

        if ($nodeType === 'internal_link' && $routeName === '' && $targetUrl === '') {
            throw new RuntimeException(__('Interna ruta ili putanja je obavezna.'));
        }

        if ($nodeType === 'internal_link' && $targetUrl !== '' && !$this->validInternalPath((string)$targetUrl)) {
            throw new RuntimeException(__('Interna putanja nije valjana.'));
        }

        $now = date('Y-m-d H:i:s');
        $values = [
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'node_type' => $nodeType,
            'slug' => $slug,
            'title' => $title,
            'document_key' => $documentKey,
            'route_name' => $routeName,
            'target_url' => $targetUrl,
            'sort_order' => $this->intValue($data['sort_order'] ?? 100),
            'is_homepage' => $nodeType === 'document' && $this->boolValue($data['is_homepage'] ?? false),
            'is_enabled' => true,
            'updated_by_user_id' => $actorUserId,
            'updated_at' => $now,
        ];

        if ($values['is_homepage']) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                ->where('workspace_id', '=', $workspaceId)
                ->update(['is_homepage' => false]);
        }

        if ($nodeId > 0) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                ->where('id', '=', $nodeId)
                ->where('workspace_id', '=', $workspaceId)
                ->where('is_enabled', '=', true)
                ->update($values);
        } else {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)->insert([
                'uuid' => $this->uuid(),
                'created_by_user_id' => $actorUserId,
                'created_at' => $now,
                ...$values,
            ]);
            $nodeId = (int)$this->database->lastInsertId();
        }

        $node = $this->findNodeById($nodeId);
        if (!is_array($node)) {
            throw new RuntimeException(__('Spremljeni čvor nije moguće učitati.'));
        }

        return $node;
    }

    /**
     * HR: Atomski zamjenjuje roditelja i redoslijed svih aktivnih čvorova jednog
     *     područja nakon potpune provjere roditelja i ciklusa.
     * EN: Atomically replaces parent and order values for every active node in
     *     one Workspace after complete parent and cycle validation.
     *
     * Početnici / Beginners:
     * HR: Cijelo se stablo provjerava prije prvog UPDATE-a, pa pogrešan raspored
     *     ne može ostaviti samo djelomično spremljenu hijerarhiju.
     * EN: The complete tree is validated before the first UPDATE, so an invalid
     *     arrangement cannot leave a partially saved hierarchy.
     *
     * @param list<array<string, mixed>> $placements
     */
    public function reorderNodes(int $workspaceId, array $placements, int $actorUserId): void
    {
        $this->assertTablesReady();
        $nodes = $this->nodesForWorkspace($workspaceId);
        if ($nodes === [] && $placements === []) {
            return;
        }

        $nodesById = [];
        foreach ($nodes as $node) {
            $nodesById[$this->intValue($node['id'] ?? 0)] = $node;
        }

        $normalized = [];
        foreach ($placements as $placement) {
            $nodeId = $this->intValue($placement['id'] ?? 0);
            if ($nodeId <= 0 || isset($normalized[$nodeId]) || !isset($nodesById[$nodeId])) {
                throw new RuntimeException(__('Raspored stabla nije potpun ili sadrži nepoznatu stranicu.'));
            }

            $normalized[$nodeId] = [
                'parent_id' => $this->nullablePositiveInt($placement['parent_id'] ?? null),
                'sort_order' => $this->intValue($placement['sort_order'] ?? 0),
            ];
        }

        $expectedIds = array_keys($nodesById);
        $submittedIds = array_keys($normalized);
        sort($expectedIds);
        sort($submittedIds);
        if (count($normalized) !== count($nodesById) || $expectedIds !== $submittedIds) {
            throw new RuntimeException(__('Raspored stabla nije potpun ili sadrži nepoznatu stranicu.'));
        }

        $parentByNode = [];
        foreach ($normalized as $nodeId => $placement) {
            $parentId = $placement['parent_id'];
            if ($parentId !== null) {
                $parent = $nodesById[$parentId] ?? null;
                if (!is_array($parent)) {
                    throw new RuntimeException(__('Roditeljska stranica nije valjana.'));
                }

                if ($this->stringValue($parent['node_type'] ?? '') !== 'document') {
                    throw new RuntimeException(__('Samo dokument-stranica može imati podređene stavke.'));
                }
            }

            $parentByNode[$nodeId] = $parentId ?? 0;
        }

        foreach (array_keys($parentByNode) as $nodeId) {
            $visited = [];
            $currentId = $nodeId;
            while ($currentId > 0) {
                if (isset($visited[$currentId])) {
                    throw new RuntimeException(__('Stranicu nije moguće premjestiti u vlastitu podgranu.'));
                }

                $visited[$currentId] = true;
                $currentId = $parentByNode[$currentId] ?? 0;
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->database->transaction(
            static function (Database $database) use (
                $workspaceId,
                $normalized,
                $actorUserId,
                $now,
            ): void {
                foreach ($normalized as $nodeId => $placement) {
                    $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                        ->where('id', '=', $nodeId)
                        ->where('workspace_id', '=', $workspaceId)
                        ->where('is_enabled', '=', true)
                        ->update([
                            'parent_id' => $placement['parent_id'],
                            'sort_order' => $placement['sort_order'],
                            'updated_by_user_id' => $actorUserId,
                            'updated_at' => $now,
                        ]);
                }
            },
        );
    }

    /**
     * HR: Onemogućuje čvor i cijelu njegovu podgranu bez fizičkog brisanja zapisa.
     * EN: Disables a node and its complete subtree without physically deleting records.
     */
    public function disableNodeTree(int $workspaceId, int $nodeId, int $actorUserId): void
    {
        $ids = $this->descendantIds($workspaceId, $nodeId);
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                ->where('id', '=', $id)
                ->where('workspace_id', '=', $workspaceId)
                ->update([
                    'is_enabled' => false,
                    'is_homepage' => false,
                    'updated_by_user_id' => $actorUserId,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * HR: Trajno briše jedan nikad objavljeni čvor, njegove ACL i workflow
     *     retke, a izravnu djecu premješta njegovu roditelju. Metoda ne služi
     *     za obične objavljene stranice koje koriste soft-delete.
     *
     * EN: Permanently deletes one never-published node and its ACL/workflow
     *     rows while reparenting direct children to its parent. This method is
     *     not used for ordinary published pages, which use soft deletion.
     */
    public function deleteUnpublishedNodePermanently(
        int $workspaceId,
        int $nodeId,
        int $actorUserId,
    ): void {
        $node = $this->findNodeById($nodeId);
        if (
            !is_array($node)
            || $this->intValue($node['workspace_id'] ?? 0) !== $workspaceId
        ) {
            throw new RuntimeException(__('Stranica nije pronađena.'));
        }

        $parentId = $this->nullablePositiveInt($node['parent_id'] ?? null);
        $now = date('Y-m-d H:i:s');
        $this->database->transaction(
            static function (Database $database) use (
                $workspaceId,
                $nodeId,
                $parentId,
                $actorUserId,
                $now,
            ): void {
                $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                    ->where('workspace_id', '=', $workspaceId)
                    ->where('parent_id', '=', $nodeId)
                    ->update([
                        'parent_id' => $parentId,
                        'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                        'updated_at' => $now,
                    ]);
                $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)
                    ->where('node_id', '=', $nodeId)
                    ->delete();
                $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_WORKFLOWS)
                    ->where('node_id', '=', $nodeId)
                    ->delete();
                $database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                    ->where('workspace_id', '=', $workspaceId)
                    ->where('id', '=', $nodeId)
                    ->delete();
            },
        );
    }

    /**
     * HR: Vraća aktivne čvorove odabrane podgrane prije brisanja ili druge skupne radnje.
     * EN: Returns active nodes in a selected subtree before deletion or another bulk action.
     *
     * @return list<array<string, mixed>>
     */
    public function nodesInSubtree(int $workspaceId, int $nodeId): array
    {
        $ids = $this->descendantIds($workspaceId, $nodeId);
        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            $this->nodesForWorkspace($workspaceId),
            fn(array $node): bool => in_array($this->intValue($node['id'] ?? 0), $ids, true),
        ));
    }

    /**
     * HR: Vraća ograničenja postavljena izravno na jednom čvoru.
     * EN: Returns restrictions assigned directly to one node.
     *
     * @return list<array<string, mixed>>
     */
    public function nodeAclRows(int $nodeId): array
    {
        $this->assertTablesReady();

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)
                ->where('node_id', '=', $nodeId)
                ->orderBy('subject_type', 'ASC')
                ->orderBy('subject_id', 'ASC')
                ->get(),
        );
    }

    /**
     * HR: Vraća sva ograničenja za zadani skup predaka u jednome prolazu.
     * EN: Returns all restrictions for a set of ancestors in one pass.
     *
     * @param list<int> $nodeIds
     * @return list<array<string, mixed>>
     */
    public function nodeAclRowsForNodes(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }

        return $this->rows(
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)
                ->whereIn('node_id', $nodeIds)
                ->get(),
        );
    }

    /**
     * HR: Zamjenjuje ograničenja čvora samo subjektima koji već imaju Workspace ACL zapis.
     * EN: Replaces node restrictions only for subjects already present in the Workspace ACL.
     *
     * @param array<string, mixed> $acl
     */
    public function replaceNodeAcl(int $workspaceId, int $nodeId, array $acl): void
    {
        $allowedSubjects = [];
        foreach ($this->workspaceAclRows($workspaceId) as $row) {
            $allowedSubjects[$this->subjectKey(
                $this->stringValue($row['subject_type'] ?? ''),
                $this->intValue($row['subject_id'] ?? 0),
            )] = true;
        }

        $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)
            ->where('node_id', '=', $nodeId)
            ->delete();
        $now = date('Y-m-d H:i:s');

        foreach (
            [
                self::SUBJECT_USER,
                self::SUBJECT_GROUP,
                self::SUBJECT_PUBLIC,
                self::SUBJECT_AUTHENTICATED,
            ] as $subjectType
        ) {
            $subjects = is_array($acl[$subjectType] ?? null) ? $acl[$subjectType] : [];
            foreach ($subjects as $subjectId => $permissions) {
                $subjectId = $this->intValue($subjectId);
                $key = $this->subjectKey($subjectType, $subjectId);
                if ($subjectId <= 0) {
                    continue;
                }

                if (!isset($allowedSubjects[$key])) {
                    continue;
                }

                if (!is_array($permissions)) {
                    continue;
                }

                $normalized = $this->permissionValues(WorkspaceValue::stringKeyArray($permissions));
                if ($subjectType === self::SUBJECT_PUBLIC) {
                    $normalized = [
                        'can_view' => $normalized['can_view'],
                        'can_add' => false,
                        'can_edit' => false,
                        'can_publish' => false,
                        'can_delete' => false,
                        'can_manage' => false,
                    ];
                }

                if (!$this->hasAnyPermission($normalized)) {
                    continue;
                }

                $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODE_ACL)->insert([
                    'node_id' => $nodeId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    ...$normalized,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * HR: Pretražuje korisnički login i display name bez učitavanja cijelog imenika.
     * EN: Searches user login and display name without loading the full directory.
     *
     * @return list<array<string, mixed>>
     */
    private function searchUsers(string $search, int $limit): array
    {
        if (!$this->database->schema()->hasTable(self::AUTH_USERS_TABLE)) {
            return [];
        }

        $usersById = [];
        $query = $this->database->table(self::AUTH_USERS_TABLE)
            ->where('is_active', '=', true)
            ->orderBy('login_identifier', 'ASC')
            ->limit($limit);
        if ($search !== '') {
            $query->where('login_identifier', 'LIKE', '%' . $search . '%');
        }

        foreach ($this->rows($query->get()) as $user) {
            $usersById[$this->intValue($user['id'] ?? 0)] = $user;
        }

        if (
            $search !== ''
            && $this->database->schema()->hasTable(self::AUTH_ATTRIBUTE_VALUES_TABLE)
        ) {
            $attributeRows = $this->rows(
                $this->database->table(self::AUTH_ATTRIBUTE_VALUES_TABLE)
                    ->select(['user_id'])
                    ->where('field_key', '=', 'display_name')
                    ->where('value_text', 'LIKE', '%' . $search . '%')
                    ->limit($limit)
                    ->get(),
            );
            $attributeUserIds = [];
            foreach ($attributeRows as $attributeRow) {
                $userId = $this->intValue($attributeRow['user_id'] ?? 0);
                if ($userId > 0) {
                    $attributeUserIds[] = $userId;
                }
            }

            if ($attributeUserIds !== []) {
                $matchingUsers = $this->rows(
                    $this->database->table(self::AUTH_USERS_TABLE)
                        ->where('is_active', '=', true)
                        ->whereIn('id', array_values(array_unique($attributeUserIds)))
                        ->get(),
                );
                foreach ($matchingUsers as $user) {
                    $usersById[$this->intValue($user['id'] ?? 0)] = $user;
                }
            }
        }

        $users = array_values($this->decorateUsers(array_values($usersById)));
        usort(
            $users,
            fn(array $left, array $right): int => strcasecmp(
                $this->stringValue($left['label'] ?? ''),
                $this->stringValue($right['label'] ?? ''),
            ),
        );

        return array_slice($users, 0, $limit);
    }

    /**
     * HR: Pretražuje stvarne grupe te u isti rezultat umeće ugrađene publike.
     * EN: Searches real groups and inserts built-in audiences into the same result.
     *
     * @return list<array<string, mixed>>
     */
    private function searchGroups(string $search, int $limit): array
    {
        $normalizedSearch = mb_strtolower($search);
        $subjects = [];
        foreach (
            [
                [self::SUBJECT_PUBLIC, __('Javno'), true],
                [self::SUBJECT_AUTHENTICATED, __('Svi prijavljeni'), false],
            ] as [$type, $label, $readOnly]
        ) {
            if ($search !== '' && !str_contains(mb_strtolower($label), $normalizedSearch)) {
                continue;
            }

            $subjects[] = [
                'id' => self::BUILT_IN_SUBJECT_ID,
                'type' => $type,
                'category' => self::SUBJECT_GROUP,
                'label' => $label,
                'is_builtin' => true,
                'is_read_only' => $readOnly,
            ];
        }

        if (!$this->database->schema()->hasTable(self::AUTH_GROUPS_TABLE)) {
            return $subjects;
        }

        $query = $this->database->table(self::AUTH_GROUPS_TABLE)
            ->where('is_enabled', '=', true)
            ->orderBy('group_name', 'ASC')
            ->limit($limit);
        if ($search !== '') {
            $query->where('group_name', 'LIKE', '%' . $search . '%');
        }

        foreach ($this->rows($query->get()) as $group) {
            $subjects[] = [
                'id' => $this->intValue($group['id'] ?? 0),
                'type' => self::SUBJECT_GROUP,
                'category' => self::SUBJECT_GROUP,
                'label' => $this->stringValue($group['group_name'] ?? ''),
                'is_builtin' => false,
                'is_read_only' => false,
            ];
        }

        return array_slice($subjects, 0, $limit);
    }

    /**
     * HR: Učitava aktivne korisnike prema ID-u i vraća ih indeksirane radi brzog spajanja s ACL-om.
     * EN: Loads active users by ID and indexes them for fast ACL merging.
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function usersByIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn(int $id): bool => $id > 0)));
        if ($userIds === [] || !$this->database->schema()->hasTable(self::AUTH_USERS_TABLE)) {
            return [];
        }

        return $this->decorateUsers($this->rows(
            $this->database->table(self::AUTH_USERS_TABLE)
                ->where('is_active', '=', true)
                ->whereIn('id', $userIds)
                ->get(),
        ));
    }

    /**
     * HR: Učitava aktivne grupe prema ID-u i indeksira ih radi brzog spajanja s ACL-om.
     * EN: Loads active groups by ID and indexes them for fast ACL merging.
     *
     * @param list<int> $groupIds
     * @return array<int, array<string, mixed>>
     */
    private function groupsByIds(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter($groupIds, static fn(int $id): bool => $id > 0)));
        if ($groupIds === [] || !$this->database->schema()->hasTable(self::AUTH_GROUPS_TABLE)) {
            return [];
        }

        $groups = [];
        foreach (
            $this->rows(
                $this->database->table(self::AUTH_GROUPS_TABLE)
                    ->where('is_enabled', '=', true)
                    ->whereIn('id', $groupIds)
                    ->get(),
            ) as $group
        ) {
            $groups[$this->intValue($group['id'] ?? 0)] = $group;
        }

        return $groups;
    }

    /**
     * HR: Grupno dodaje display name korisnicima i indeksira rezultat po ID-u.
     * EN: Adds display names to users in one batch and indexes the result by ID.
     *
     * @param list<array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function decorateUsers(array $users): array
    {
        $ids = [];
        foreach ($users as $user) {
            $userId = $this->intValue($user['id'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        $displayNames = [];
        if ($ids !== [] && $this->database->schema()->hasTable(self::AUTH_ATTRIBUTE_VALUES_TABLE)) {
            $attributes = $this->rows(
                $this->database->table(self::AUTH_ATTRIBUTE_VALUES_TABLE)
                    ->select(['user_id', 'value_text'])
                    ->where('field_key', '=', 'display_name')
                    ->whereIn('user_id', array_values(array_unique($ids)))
                    ->get(),
            );
            foreach ($attributes as $attribute) {
                $userId = $this->intValue($attribute['user_id'] ?? 0);
                $label = $this->stringValue($attribute['value_text'] ?? '');
                if ($userId > 0 && $label !== '') {
                    $displayNames[$userId] = $label;
                }
            }
        }

        $decorated = [];
        foreach ($users as $user) {
            $userId = $this->intValue($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $user['label'] = $displayNames[$userId]
            ?? $this->stringValue($user['login_identifier'] ?? __('Korisnik'));
            $user['type'] = self::SUBJECT_USER;
            $user['category'] = self::SUBJECT_USER;
            $user['is_builtin'] = false;
            $user['is_read_only'] = false;
            $decorated[$userId] = $user;
        }

        return $decorated;
    }

    /**
     * HR: Vraća ID-eve grupa kojima korisnik trenutačno pripada.
     * EN: Returns IDs of groups to which the user currently belongs.
     *
     * @return list<int>
     */
    public function groupIdsForUser(int $userId): array
    {
        if ($userId <= 0 || !$this->database->schema()->hasTable(self::AUTH_USER_GROUPS_TABLE)) {
            return [];
        }

        $rows = $this->database->table(self::AUTH_USER_GROUPS_TABLE)
            ->select(['group_id'])
            ->where('user_id', '=', $userId)
            ->get();
        $ids = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_numeric($row['group_id'] ?? null)) {
                $ids[] = (int)$row['group_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * HR: Vraća minimalni skup aktivnih Auth korisnika potreban za grupni ACL
     *     izračun primatelja obavijesti.
     * EN: Returns the minimal active Auth user set required for batch ACL
     *     notification-recipient calculation.
     *
     * @return list<array<string, mixed>>
     */
    public function activeAclUsers(): array
    {
        if (!$this->database->schema()->hasTable(self::AUTH_USERS_TABLE)) {
            return [];
        }

        return $this->rows(
            $this->database->table(self::AUTH_USERS_TABLE)
                ->select(['id', 'is_admin'])
                ->where('is_active', '=', true)
                ->orderBy('id', 'ASC')
                ->get(),
        );
    }

    /**
     * HR: U jednom upitu grupira sva Auth članstva zadanih korisnika po
     *     korisničkom ID-u.
     * EN: Loads all Auth memberships for the supplied users in one query and
     *     groups them by user ID.
     *
     * @param list<int> $userIds
     * @return array<int, list<int>>
     */
    public function groupIdsForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            $userIds,
            static fn(int $userId): bool => $userId > 0,
        )));
        if ($userIds === [] || !$this->database->schema()->hasTable(self::AUTH_USER_GROUPS_TABLE)) {
            return [];
        }

        $groupsByUser = [];
        $rows = $this->database->table(self::AUTH_USER_GROUPS_TABLE)
            ->select(['user_id', 'group_id'])
            ->whereIn('user_id', $userIds)
            ->get();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!is_numeric($row['user_id'] ?? null)) {
                continue;
            }

            if (!is_numeric($row['group_id'] ?? null)) {
                continue;
            }

            $userId = (int)$row['user_id'];
            $groupId = (int)$row['group_id'];
            if ($userId > 0 && $groupId > 0) {
                $groupsByUser[$userId][$groupId] = $groupId;
            }
        }

        foreach ($groupsByUser as $userId => $groupIds) {
            $groupsByUser[$userId] = array_values($groupIds);
        }

        return $groupsByUser;
    }

    /**
     * HR: Gradi lanac predaka od korijena do odabranog čvora.
     * EN: Builds the ancestor chain from the root to the selected node.
     *
     * @return list<int>
     */
    public function ancestorNodeIds(int $workspaceId, int $nodeId): array
    {
        $nodes = $this->nodesForWorkspace($workspaceId);
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$this->intValue($node['id'] ?? 0)] = $node;
        }

        $ids = [];
        $currentId = $nodeId;
        $visited = [];
        while ($currentId > 0 && isset($byId[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $ids[] = $currentId;
            $currentId = $this->intValue($byId[$currentId]['parent_id'] ?? 0);
        }

        return array_reverse($ids);
    }

    /**
     * HR: Pretvara proizvoljne retke ORM-a u sigurnu listu polja.
     * EN: Converts arbitrary ORM rows into a safe list of arrays.
     *
     * @param mixed[] $rows
     * @return list<array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return WorkspaceValue::rows($rows);
    }

    /**
     * HR: Normalizira jedan proizvoljni ORM red ili vraća null.
     * EN: Normalizes one arbitrary ORM row or returns null.
     *
     * @return array<string, mixed>|null
     */
    private function row(mixed $row): ?array
    {
        $normalized = WorkspaceValue::stringKeyArray($row);

        return $normalized !== [] ? $normalized : null;
    }

    /**
     * HR: Odbija operaciju kada inicijalna migracija nedostaje.
     * EN: Rejects an operation when the initial migration is missing.
     */
    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Workspace migracija još nije pokrenuta.'));
        }
    }

    /**
     * HR: Normalizira vidljivost na podržani skup vrijednosti.
     * EN: Normalizes visibility to the supported value set.
     */
    private function visibility(mixed $value): string
    {
        $visibility = is_scalar($value) ? strtolower(trim((string)$value)) : '';

        return in_array($visibility, ['restricted', 'authenticated', 'public'], true)
        ? $visibility
        : 'restricted';
    }

    /**
     * HR: Normalizira tip čvora na dokument ili podržani link.
     * EN: Normalizes node type to a document or supported link.
     */
    private function nodeType(mixed $value): string
    {
        $type = is_scalar($value) ? strtolower(trim((string)$value)) : '';

        return in_array($type, ['document', 'internal_link', 'external_link'], true)
        ? $type
        : 'document';
    }

    /**
     * HR: Gradi jedinstveni slug područja uz predvidljiv numerički nastavak.
     * EN: Builds a unique workspace slug with a predictable numeric suffix.
     */
    private function uniqueWorkspaceSlug(string $base, int $ignoreId): string
    {
        $candidate = $base;
        $counter = 2;
        while (true) {
            $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACES)
                ->where('slug', '=', $candidate)
                ->first();
            if (!is_array($row) || $this->intValue($row['id'] ?? 0) === $ignoreId) {
                return $candidate;
            }

            $candidate = $base . '-' . $counter;
            ++$counter;
        }
    }

    /**
     * HR: Gradi jedinstveni slug čvora unutar jednog područja.
     * EN: Builds a node slug unique within one workspace.
     */
    private function uniqueNodeSlug(int $workspaceId, string $base, int $ignoreId): string
    {
        $candidate = $base;
        $counter = 2;
        while (true) {
            $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_NODES)
                ->where('workspace_id', '=', $workspaceId)
                ->where('slug', '=', $candidate)
                ->first();
            if (!is_array($row) || $this->intValue($row['id'] ?? 0) === $ignoreId) {
                return $candidate;
            }

            $candidate = $base . '-' . $counter;
            ++$counter;
        }
    }

    /**
     * HR: Provjerava bi li novi roditelj stvorio ciklus stabla.
     * EN: Checks whether a new parent would create a tree cycle.
     */
    private function wouldCreateCycle(int $nodeId, int $parentId): bool
    {
        $currentId = $parentId;
        $visited = [];
        while ($currentId > 0 && !isset($visited[$currentId])) {
            if ($currentId === $nodeId) {
                return true;
            }

            $visited[$currentId] = true;
            $node = $this->findNodeById($currentId);
            if (!is_array($node)) {
                return false;
            }

            $currentId = $this->intValue($node['parent_id'] ?? 0);
        }

        return false;
    }

    /**
     * HR: Vraća ID-eve čvora i svih aktivnih potomaka.
     * EN: Returns IDs of a node and all active descendants.
     *
     * @return list<int>
     */
    private function descendantIds(int $workspaceId, int $nodeId): array
    {
        $nodes = $this->nodesForWorkspace($workspaceId);
        $children = [];
        foreach ($nodes as $node) {
            $parentId = $this->intValue($node['parent_id'] ?? 0);
            $children[$parentId][] = $this->intValue($node['id'] ?? 0);
        }

        $result = [];
        $pending = [$nodeId];
        while ($pending !== []) {
            $current = array_shift($pending);
            if (!is_int($current)) {
                continue;
            }

            if ($current <= 0) {
                continue;
            }

            if (in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($children[$current] ?? [] as $childId) {
                $pending[] = $childId;
            }
        }

        return $result;
    }

    /**
     * HR: Provjerava postoji li auth subjekt odabran u ACL formi.
     * EN: Checks whether an auth subject selected in the ACL form exists.
     */
    private function subjectExists(string $type, int $id): bool
    {
        if (
            in_array($type, [self::SUBJECT_PUBLIC, self::SUBJECT_AUTHENTICATED], true)
            && $id === self::BUILT_IN_SUBJECT_ID
        ) {
            return true;
        }

        return $type === self::SUBJECT_USER
        ? $this->userExists($id)
        : ($type === self::SUBJECT_GROUP && $this->groupExists($id));
    }

    /**
     * HR: Provjerava postoji li aktivni korisnik.
     * EN: Checks whether an active user exists.
     */
    private function userExists(int $id): bool
    {
        if (!$this->database->schema()->hasTable(self::AUTH_USERS_TABLE)) {
            return false;
        }

        return is_array(
            $this->database->table(self::AUTH_USERS_TABLE)
                ->where('id', '=', $id)
                ->where('is_active', '=', true)
                ->first(),
        );
    }

    /**
     * HR: Provjerava postoji li aktivna grupa.
     * EN: Checks whether an active group exists.
     */
    private function groupExists(int $id): bool
    {
        if (!$this->database->schema()->hasTable(self::AUTH_GROUPS_TABLE)) {
            return false;
        }

        return is_array(
            $this->database->table(self::AUTH_GROUPS_TABLE)
                ->where('id', '=', $id)
                ->where('is_enabled', '=', true)
                ->first(),
        );
    }

    /**
     * HR: Pri kreiranju područja pretvara zadanu vidljivost u isti ugrađeni ACL
     *     model koji kasnije uređuje administrator.
     * EN: Converts default visibility into the same built-in ACL model edited
     *     by administrators when a Workspace is created.
     */
    private function insertBuiltInVisibilityAcl(int $workspaceId, string $visibility, string $now): void
    {
        if (!in_array($visibility, [self::SUBJECT_PUBLIC, self::SUBJECT_AUTHENTICATED], true)) {
            return;
        }

        $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_ACL)->insert([
            'workspace_id' => $workspaceId,
            'subject_type' => $visibility,
            'subject_id' => self::BUILT_IN_SUBJECT_ID,
            'can_view' => true,
            'can_add' => false,
            'can_edit' => false,
            'can_publish' => false,
            'can_delete' => false,
            'can_manage' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * HR: Normalizira polja ovlasti i osigurava da manage uključuje sva niža prava.
     * EN: Normalizes permission fields and ensures manage includes every lower permission.
     *
     * @param array<string, mixed> $permissions
     * @return array<string, bool>
     */
    private function permissionValues(array $permissions): array
    {
        $manage = $this->boolValue($permissions['can_manage'] ?? false);
        $publish = $manage || $this->boolValue($permissions['can_publish'] ?? false);
        $delete = $manage || $this->boolValue($permissions['can_delete'] ?? false);
        $edit = $delete || $this->boolValue($permissions['can_edit'] ?? false);
        $add = $manage || $this->boolValue($permissions['can_add'] ?? false);
        $view = $add || $edit || $publish || $this->boolValue($permissions['can_view'] ?? false);

        return [
            'can_view' => $view,
            'can_add' => $add,
            'can_edit' => $edit,
            'can_publish' => $publish,
            'can_delete' => $delete,
            'can_manage' => $manage,
        ];
    }

    /**
     * HR: Provjerava sadrži li normalizirani red barem jedno pravo.
     * EN: Checks whether a normalized row contains at least one permission.
     *
     * @param array<string, bool> $permissions
     */
    private function hasAnyPermission(array $permissions): bool
    {
        return in_array(true, $permissions, true);
    }

    /**
     * HR: Gradi stabilni ključ tipa i ID-a ACL subjekta.
     * EN: Builds a stable ACL subject type-and-ID key.
     */
    private function subjectKey(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * HR: Normalizira naslov i druge kratke tekstualne vrijednosti.
     * EN: Normalizes titles and other short text values.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Čita cijeli broj iz forme ili baze.
     * EN: Reads an integer from form or database input.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Vraća pozitivan ID ili null za korijenski čvor.
     * EN: Returns a positive ID or null for a root node.
     */
    private function nullablePositiveInt(mixed $value): ?int
    {
        $id = $this->intValue($value);

        return $id > 0 ? $id : null;
    }

    /**
     * HR: Razlikuje izričitu null vrijednost od polja koje prijelaz nije
     *     mijenjao, što omogućuje sigurno čišćenje starih workflow oznaka.
     * EN: Distinguishes an explicit null from a field omitted by a transition,
     *     allowing stale workflow markers to be cleared safely.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed>|null $existing
     */
    private function workflowValue(array $values, ?array $existing, string $key): mixed
    {
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return is_array($existing) && array_key_exists($key, $existing)
        ? $existing[$key]
        : null;
    }

    /**
     * HR: Normalizira kratku oznaku jezika za jedinstveni workflow ključ.
     * EN: Normalizes a short locale code for the unique workflow key.
     */
    private function language(string $language): string
    {
        $language = strtolower(trim($language));
        $language = (string)preg_replace('/[^a-z0-9_-]+/', '', $language);

        return $language !== '' ? substr($language, 0, 16) : 'hr';
    }

    /**
     * HR: Pretvara skalarne checkbox vrijednosti u boolean.
     * EN: Converts scalar checkbox values into a boolean.
     */
    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * HR: Gradi siguran ASCII slug.
     * EN: Builds a safe ASCII slug.
     */
    private function slug(mixed $value, string $fallback): string
    {
        $value = $this->stringValue($value);
        $value = strtr($value, [
            'č' => 'c',
            'ć' => 'c',
            'đ' => 'd',
            'š' => 's',
            'ž' => 'z',
            'Č' => 'c',
            'Ć' => 'c',
            'Đ' => 'd',
            'Š' => 's',
            'Ž' => 'z',
        ]);
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * HR: Provjerava apsolutni HTTP(S) URL vanjskog linka.
     * EN: Validates an absolute HTTP(S) URL for an external link.
     */
    private function validExternalUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        return in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        && trim((string)($parts['host'] ?? '')) !== '';
    }

    /**
     * HR: Dopušta samo lokalnu apsolutnu putanju bez protokola, drugog hosta ili obrnutih kosa crta.
     * EN: Allows only a local absolute path without a scheme, another host, or backslashes.
     */
    private function validInternalPath(string $path): bool
    {
        return str_starts_with($path, '/')
        && !str_starts_with($path, '//')
        && !str_contains($path, '\\')
        && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    /**
     * HR: Generira prenosivi UUID v4 bez dodatne biblioteke.
     * EN: Generates a portable UUID v4 without an additional library.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
