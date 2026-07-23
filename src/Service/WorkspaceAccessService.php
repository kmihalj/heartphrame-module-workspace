<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use HeartPhrame\Authn\AuthnHandlerInterface;

use function is_array;
use function is_numeric;

final readonly class WorkspaceAccessService
{
    private const PERMISSION_KEYS = [
        'can_view',
        'can_add',
        'can_edit',
        'can_publish',
        'can_delete',
        'can_manage',
    ];

    /**
     * HR: Prima repozitorij, auth kontekst i konfiguraciju potrebnu za jedinstveni ACL izračun.
     * EN: Receives the repository, auth context, and configuration required for one ACL calculation.
     */
    public function __construct(
        private WorkspaceRepository $repository,
        private AuthnHandlerInterface $authnHandler,
        private WorkspaceConfig $config,
        private WorkspaceWorkflowService $workflow,
    ) {
    }

    /**
     * HR: Vraća normalizirani session payload trenutnog korisnika ili null za gosta.
     * EN: Returns the normalized current-user session payload or null for a guest.
     *
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        $user = $this->authnHandler->userData();

        return is_array($user) ? $this->stringKeyArray($user) : null;
    }

    /**
     * HR: Provjerava administratorski status trenutnog ili proslijeđenog korisnika.
     * EN: Checks administrator status for the current or supplied user.
     *
     * @param array<string, mixed>|null $user
     */
    public function isAdministrator(?array $user = null): bool
    {
        $user ??= $this->currentUser();

        return is_array($user) && (bool)($user['is_admin'] ?? false);
    }

    /**
     * HR: Vraća smije li korisnik kreirati novo područje.
     * EN: Returns whether the user may create a new workspace.
     *
     * @param array<string, mixed>|null $user
     */
    public function canCreateWorkspace(?array $user = null): bool
    {
        $user ??= $this->currentUser();
        if (!is_array($user) || $this->userId($user) <= 0) {
            return false;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        return $this->config->authenticatedUsersMayCreate();
    }

    /**
     * HR: Filtrira područja koja korisnik stvarno smije vidjeti.
     * EN: Filters workspaces the user may actually view.
     *
     * @param array<string, mixed>|null $user
     * @return list<array<string, mixed>>
     */
    public function visibleWorkspaces(?array $user = null): array
    {
        $user ??= $this->currentUser();
        $visible = [];
        foreach ($this->repository->activeWorkspaces() as $workspace) {
            $permissions = $this->workspacePermissions($workspace, $user);
            if (!$permissions['can_view']) {
                continue;
            }

            $workspace['permissions'] = $permissions;
            $visible[] = $workspace;
        }

        return $visible;
    }

    /**
     * HR: Računa bazna prava područja kao uniju direktnog korisničkog i grupnog ACL-a.
     * EN: Calculates base workspace permissions as the union of direct user and group ACL entries.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, mixed>|null $user
     * @return array<string, bool>
     */
    public function workspacePermissions(array $workspace, ?array $user = null): array
    {
        $user ??= $this->currentUser();
        $userId = $this->userId($user);
        $groupIds = $userId > 0 ? $this->repository->groupIdsForUser($userId) : [];

        return $this->workspacePermissionsFromRows(
            $workspace,
            $user,
            $groupIds,
            $this->repository->workspaceAclRows(WorkspaceValue::int($workspace['id'] ?? 0)),
        );
    }

    /**
     * HR: Primjenjuje ograničenja od korijena do čvora kao presjek naslijeđenih prava.
     * EN: Applies restrictions from root to node as an intersection of inherited permissions.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, mixed> $node
     * @param array<string, mixed>|null $user
     * @return array<string, bool>
     */
    public function nodePermissions(array $workspace, array $node, ?array $user = null): array
    {
        $user ??= $this->currentUser();
        $base = $this->workspacePermissions($workspace, $user);
        $userId = $this->userId($user);
        if (
            $this->isAdministrator($user)
            || ($userId > 0 && $userId === WorkspaceValue::int($workspace['owner_user_id'] ?? 0))
        ) {
            return $base;
        }

        $ancestorIds = $this->repository->ancestorNodeIds(
            WorkspaceValue::int($workspace['id'] ?? 0),
            WorkspaceValue::int($node['id'] ?? 0),
        );
        $rowsByNode = [];
        foreach ($this->repository->nodeAclRowsForNodes($ancestorIds) as $row) {
            $rowsByNode[WorkspaceValue::int($row['node_id'] ?? 0)][] = $row;
        }

        $groupIds = $userId > 0 ? $this->repository->groupIdsForUser($userId) : [];

        return $this->restrictPermissionsFromRows(
            $base,
            $ancestorIds,
            $rowsByNode,
            $userId,
            $groupIds,
        );
    }

    /**
     * HR: Grupno vraća aktivne korisnike koji na odabranom čvoru imaju traženo
     *     efektivno pravo, uključujući vlasnika i administratore.
     * EN: Returns active users who hold the requested effective permission on
     *     the selected node in one batch, including the owner and administrators.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, mixed> $node
     * @return list<int>
     */
    public function userIdsWithNodePermission(
        array $workspace,
        array $node,
        string $permission,
    ): array {
        if (!in_array($permission, self::PERMISSION_KEYS, true)) {
            return [];
        }

        $users = $this->repository->activeAclUsers();
        $userIds = [];
        foreach ($users as $user) {
            $userId = $this->userId($user);
            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }

        $groupsByUser = $this->repository->groupIdsForUsers($userIds);
        $workspaceRows = $this->repository->workspaceAclRows(
            WorkspaceValue::int($workspace['id'] ?? 0),
        );
        $ancestorIds = $this->repository->ancestorNodeIds(
            WorkspaceValue::int($workspace['id'] ?? 0),
            WorkspaceValue::int($node['id'] ?? 0),
        );
        $rowsByNode = [];
        foreach ($this->repository->nodeAclRowsForNodes($ancestorIds) as $row) {
            $rowsByNode[WorkspaceValue::int($row['node_id'] ?? 0)][] = $row;
        }

        $allowedUserIds = [];
        foreach ($users as $user) {
            $userId = $this->userId($user);
            if ($userId <= 0) {
                continue;
            }

            $permissions = $this->workspacePermissionsFromRows(
                $workspace,
                $user,
                $groupsByUser[$userId] ?? [],
                $workspaceRows,
            );
            if (
                !$this->isAdministrator($user)
                && $userId !== WorkspaceValue::int($workspace['owner_user_id'] ?? 0)
            ) {
                $permissions = $this->restrictPermissionsFromRows(
                    $permissions,
                    $ancestorIds,
                    $rowsByNode,
                    $userId,
                    $groupsByUser[$userId] ?? [],
                );
            }

            if ($permissions[$permission]) {
                $allowedUserIds[] = $userId;
            }
        }

        return $allowedUserIds;
    }

    /**
     * HR: Vraća vidljivo stablo s efektivnim pravima svakog čvora.
     * EN: Returns the visible tree with effective permissions for every node.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, mixed>|null $user
     * @return list<array<string, mixed>>
     */
    public function visibleTree(
        array $workspace,
        ?array $user = null,
        string $language = '',
    ): array {
        $user ??= $this->currentUser();
        $visible = [];
        foreach ($this->repository->nodesForWorkspace(WorkspaceValue::int($workspace['id'] ?? 0)) as $node) {
            $permissions = $this->nodePermissions($workspace, $node, $user);
            if (!$permissions['can_view']) {
                continue;
            }

            if (
                $language !== ''
                && WorkspaceValue::string($node['node_type'] ?? '') === 'document'
                && !$permissions['can_edit']
                && !$permissions['can_publish']
                && !$permissions['can_manage']
                && !$this->workflow->isReadable(
                    WorkspaceValue::int($node['id'] ?? 0),
                    $language,
                )
            ) {
                continue;
            }

            $node['permissions'] = $permissions;
            $visible[] = $node;
        }

        return $this->buildTree($visible, null);
    }

    /**
     * HR: Provjerava editorov dokument kroz njegov Workspace čvor i nasljedni ACL.
     * EN: Checks an editor document through its Workspace node and inherited ACL.
     */
    public function canUseDocument(string $documentKey, string $permission): bool
    {
        if (!in_array($permission, self::PERMISSION_KEYS, true)) {
            return false;
        }

        $node = $this->repository->findNodeByDocumentKey($documentKey);
        if (!is_array($node)) {
            return false;
        }

        $workspace = $this->repository->findWorkspaceById(WorkspaceValue::int($node['workspace_id'] ?? 0));
        if (!is_array($workspace)) {
            return false;
        }

        $permissions = $this->nodePermissions($workspace, $node);

        return $permissions[$permission];
    }

    /**
     * HR: Provjerava pripada li ACL red gostima, svim prijavljenima, trenutnom
     *     korisniku ili jednoj njegovoj stvarnoj Auth grupi.
     * EN: Checks whether an ACL row belongs to guests, all authenticated users,
     *     the current user, or one of their real Auth groups.
     *
     * @param array<string, mixed> $row
     * @param list<int> $groupIds
     */
    private function subjectMatches(array $row, int $userId, array $groupIds): bool
    {
        $subjectType = WorkspaceValue::string($row['subject_type'] ?? '');
        $subjectId = WorkspaceValue::int($row['subject_id'] ?? null);

        return ($subjectType === WorkspaceRepository::SUBJECT_PUBLIC
            && $subjectId === WorkspaceRepository::BUILT_IN_SUBJECT_ID)
        || ($subjectType === WorkspaceRepository::SUBJECT_AUTHENTICATED
            && $subjectId === WorkspaceRepository::BUILT_IN_SUBJECT_ID
            && $userId > 0)
        || ($subjectType === WorkspaceRepository::SUBJECT_USER
            && $userId > 0
            && $subjectId === $userId)
        || ($subjectType === WorkspaceRepository::SUBJECT_GROUP
            && in_array($subjectId, $groupIds, true));
    }

    /**
     * HR: Računa bazna prava iz već učitanih Workspace ACL redaka.
     * EN: Calculates base permissions from preloaded Workspace ACL rows.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, mixed>|null $user
     * @param list<int> $groupIds
     * @param list<array<string, mixed>> $rows
     * @return array<string, bool>
     */
    private function workspacePermissionsFromRows(
        array $workspace,
        ?array $user,
        array $groupIds,
        array $rows,
    ): array {
        $permissions = $this->emptyPermissions();
        $userId = $this->userId($user);
        $isOwner = $userId > 0
        && $userId === WorkspaceValue::int($workspace['owner_user_id'] ?? 0);
        if ($this->isAdministrator($user) || $isOwner) {
            return $this->applyArchivedReadOnly($workspace, $this->allPermissions());
        }

        $visibility = WorkspaceValue::string($workspace['visibility'] ?? 'restricted');
        if ($visibility === 'public' || ($visibility === 'authenticated' && $userId > 0)) {
            $permissions['can_view'] = true;
        }

        foreach ($rows as $row) {
            if (!$this->subjectMatches($row, $userId, $groupIds)) {
                continue;
            }

            $permissions = $this->unionPermissions(
                $permissions,
                $this->permissionsFromRow($row),
            );
        }

        return $this->applyArchivedReadOnly($workspace, $permissions);
    }

    /**
     * HR: Primjenjuje već učitana ograničenja svih predaka na bazna prava.
     * EN: Applies preloaded restrictions from all ancestors to base permissions.
     *
     * @param array<string, bool> $permissions
     * @param list<int> $ancestorIds
     * @param array<int, list<array<string, mixed>>> $rowsByNode
     * @param list<int> $groupIds
     * @return array<string, bool>
     */
    private function restrictPermissionsFromRows(
        array $permissions,
        array $ancestorIds,
        array $rowsByNode,
        int $userId,
        array $groupIds,
    ): array {
        foreach ($ancestorIds as $ancestorId) {
            $restrictionRows = $rowsByNode[$ancestorId] ?? [];
            if ($restrictionRows === []) {
                continue;
            }

            $allowedAtNode = $this->emptyPermissions();
            foreach ($restrictionRows as $row) {
                if ($this->subjectMatches($row, $userId, $groupIds)) {
                    $allowedAtNode = $this->unionPermissions(
                        $allowedAtNode,
                        $this->permissionsFromRow($row),
                    );
                }
            }

            foreach (self::PERMISSION_KEYS as $key) {
                $permissions[$key] = $permissions[$key] && $allowedAtNode[$key];
            }
        }

        return $permissions;
    }

    /**
     * HR: Normalizira jedan DB ACL red u skup prava.
     * EN: Normalizes one database ACL row into a permission set.
     *
     * @param array<string, mixed> $row
     * @return array<string, bool>
     */
    private function permissionsFromRow(array $row): array
    {
        $manage = (bool)($row['can_manage'] ?? false);
        $publish = $manage || (bool)($row['can_publish'] ?? false);
        $delete = $manage || (bool)($row['can_delete'] ?? false);
        $edit = $delete || (bool)($row['can_edit'] ?? false);
        $add = $manage || (bool)($row['can_add'] ?? false);
        $view = $add || $edit || $publish || (bool)($row['can_view'] ?? false);

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
     * HR: Spaja prava iz više korisničkih ili grupnih ACL redaka.
     * EN: Unions permissions from multiple user or group ACL rows.
     *
     * @param array<string, bool> $left
     * @param array<string, bool> $right
     * @return array<string, bool>
     */
    private function unionPermissions(array $left, array $right): array
    {
        foreach (self::PERMISSION_KEYS as $key) {
            $left[$key] = $left[$key] || $right[$key];
        }

        return $left;
    }

    /**
     * HR: Vraća početni skup bez prava.
     * EN: Returns an initial permission set with no grants.
     *
     * @return array<string, bool>
     */
    private function emptyPermissions(): array
    {
        return [
            'can_view' => false,
            'can_add' => false,
            'can_edit' => false,
            'can_publish' => false,
            'can_delete' => false,
            'can_manage' => false,
        ];
    }

    /**
     * HR: Vraća puni skup prava za administratora i vlasnika područja.
     * EN: Returns the complete permission set for administrators and workspace owners.
     *
     * @return array<string, bool>
     */
    private function allPermissions(): array
    {
        return [
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
            'can_manage' => true,
        ];
    }

    /**
     * HR: Arhivirano područje ostavlja pregled i upravljanje postavkama, ali svima isključuje promjene sadržaja.
     * EN: An archived workspace keeps viewing and settings management while disabling content changes for everyone.
     *
     * @param array<string, mixed> $workspace
     * @param array<string, bool> $permissions
     * @return array<string, bool>
     */
    private function applyArchivedReadOnly(array $workspace, array $permissions): array
    {
        if (!(bool)($workspace['is_archived'] ?? false)) {
            return $permissions;
        }

        $permissions['can_add'] = false;
        $permissions['can_edit'] = false;
        $permissions['can_publish'] = false;
        $permissions['can_delete'] = false;

        return $permissions;
    }

    /**
     * HR: Rekurzivno slaže ravne čvorove u stablo bez gubitka sortiranog redoslijeda.
     * EN: Recursively turns flat nodes into a tree without losing sorted order.
     *
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $nodes, ?int $parentId): array
    {
        $branch = [];
        foreach ($nodes as $node) {
            $nodeParentId = is_numeric($node['parent_id'] ?? null) ? (int)$node['parent_id'] : null;
            if ($nodeParentId !== $parentId) {
                continue;
            }

            $node['children'] = $this->buildTree($nodes, WorkspaceValue::int($node['id'] ?? 0));
            $branch[] = $node;
        }

        return $branch;
    }

    /**
     * HR: Čita pozitivan user ID iz auth payload-a.
     * EN: Reads a positive user ID from the authentication payload.
     *
     * @param array<string, mixed>|null $user
     */
    private function userId(?array $user): int
    {
        return is_array($user) && is_numeric($user['id'] ?? null) ? (int)$user['id'] : 0;
    }

    /**
     * HR: Zadržava samo string ključeve iz auth payload-a.
     * EN: Keeps only string keys from the authentication payload.
     *
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
