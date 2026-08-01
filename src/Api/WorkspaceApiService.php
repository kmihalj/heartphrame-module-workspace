<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Api;

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;

/**
 * HR: Izlaže stabilne Workspace operacije bez ovisnosti o HTTP ili API modulu.
 *
 * EN: Exposes stable Workspace operations without depending on HTTP or the API module.
 *
 * Početnici / Beginners:
 * HR: API scope samo dopušta ulazak u operaciju. Ovaj servis nakon toga uvijek
 * ponovno provjerava stvarna prava korisnika kojem API ključ pripada.
 * EN: An API scope only permits entering an operation. This service then always
 * checks the actual rights of the user who owns the API key.
 */
final readonly class WorkspaceApiService
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
     * HR: Prima repozitorij, ACL servis i zadane postavke područja.
     *
     * EN: Receives the repository, ACL service, and Workspace defaults.
     */
    public function __construct(
        private WorkspaceRepository $repository,
        private WorkspaceAccessService $access,
        private WorkspaceConfig $config,
    ) {
    }

    /**
     * HR: Vraća sva područja koja autentificirani korisnik smije vidjeti.
     *
     * EN: Returns all workspaces visible to the authenticated user.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function listWorkspaces(array $user): array
    {
        $workspaces = [];
        foreach ($this->access->visibleWorkspaces($user) as $workspace) {
            $workspaces[] = $this->workspaceDto($workspace);
        }

        return $workspaces;
    }

    /**
     * HR: Vraća jedno vidljivo područje ili skriveni 404 kada korisnik nema pravo pregleda.
     *
     * EN: Returns one visible workspace or a concealed 404 when the user lacks view permission.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function getWorkspace(string $slug, array $user): array
    {
        return $this->workspaceDto($this->requireWorkspace($slug, $user, 'can_view'));
    }

    /**
     * HR: Vraća ACL-filtrirano stablo područja za odabrani jezik.
     *
     * EN: Returns the ACL-filtered Workspace tree for the selected language.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function getTree(string $slug, array $user, string $language = ''): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_view');

        return $this->treeDtos($this->access->visibleTree($workspace, $user, $language));
    }

    /**
     * HR: Kreira područje samo ako korisnik smije stvarati područja u aplikaciji.
     *
     * EN: Creates a Workspace only when the user may create workspaces in the application.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function createWorkspace(array $payload, array $user): array
    {
        if (!$this->access->canCreateWorkspace($user)) {
            throw $this->forbidden(__('Nemate pravo kreirati područja.'));
        }

        $actorUserId = $this->userId($user);
        if (!$this->access->isAdministrator($user)) {
            $payload['owner_user_id'] = $actorUserId;
        }

        if (!array_key_exists('visibility', $payload)) {
            $payload['visibility'] = $this->config->defaultVisibility();
        }

        $saved = $this->repository->saveWorkspace($payload, $actorUserId);
        $this->access->clearRequestCache();

        return $this->workspaceDtoWithPermissions($saved, $user);
    }

    /**
     * HR: Djelomično mijenja područje uz can_manage i čuva vlasnika neadministratoru.
     *
     * EN: Partially updates a Workspace with can_manage and preserves ownership for non-admins.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function updateWorkspace(string $slug, array $payload, array $user): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $payload = $this->mergeWorkspacePayload($workspace, $payload);
        $payload['id'] = WorkspaceValue::int($workspace['id'] ?? 0);
        if (!$this->access->isAdministrator($user)) {
            $payload['owner_user_id'] = WorkspaceValue::int($workspace['owner_user_id'] ?? 0);
        }

        $saved = $this->repository->saveWorkspace($payload, $this->userId($user));
        $this->access->clearRequestCache();

        return $this->workspaceDtoWithPermissions($saved, $user);
    }

    /**
     * HR: Soft-briše područje uz efektivno can_manage pravo.
     *
     * EN: Soft-deletes a Workspace with the effective can_manage permission.
     *
     * @param array<string,mixed> $user
     */
    public function deleteWorkspace(string $slug, array $user): void
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $this->repository->softDeleteWorkspace(
            WorkspaceValue::int($workspace['id'] ?? 0),
            $this->userId($user),
        );
        $this->access->clearRequestCache();
    }

    /**
     * HR: Administratoru vraća soft-obrisana područja.
     *
     * EN: Returns soft-deleted workspaces to an administrator.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function listDeletedWorkspaces(array $user): array
    {
        $this->requireAdministrator($user);

        $workspaces = [];
        foreach ($this->repository->deletedWorkspaces() as $workspace) {
            $workspaces[] = $this->workspaceDto($workspace);
        }

        return $workspaces;
    }

    /**
     * HR: Administratoru vraća soft-obrisano područje pod dostupnim slugom.
     *
     * EN: Restores a soft-deleted Workspace under an available slug for an administrator.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function restoreWorkspace(
        int $workspaceId,
        string $preferredSlug,
        array $user,
    ): array {
        $this->requireAdministrator($user);
        $restored = $this->repository->restoreWorkspace(
            $workspaceId,
            $preferredSlug,
            $this->userId($user),
        );
        $this->access->clearRequestCache();

        return $this->workspaceDtoWithPermissions($restored, $user);
    }

    /**
     * HR: Vraća izravni ACL područja korisniku s can_manage pravom.
     *
     * EN: Returns the direct Workspace ACL to a user with can_manage permission.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function getWorkspaceAcl(string $slug, array $user): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');

        $subjects = [];
        foreach (
            $this->repository->workspaceAclSubjects(
                WorkspaceValue::int($workspace['id'] ?? 0),
            ) as $subject
        ) {
            $subjects[] = $this->aclSubjectDto($subject);
        }

        return $subjects;
    }

    /**
     * HR: Zamjenjuje cjelokupni ACL područja validiranim popisom subjekata.
     *
     * EN: Replaces the complete Workspace ACL with a validated subject list.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function replaceWorkspaceAcl(string $slug, array $payload, array $user): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $workspaceId = WorkspaceValue::int($workspace['id'] ?? 0);
        $this->repository->replaceWorkspaceAcl($workspaceId, $this->aclMap($payload));
        $this->access->clearRequestCache();

        return $this->getWorkspaceAcl($slug, $user);
    }

    /**
     * HR: Pretražuje ograničeni imenik korisnika ili grupa za ACL picker.
     *
     * EN: Searches the bounded user or group directory for an ACL picker.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function searchAclSubjects(
        string $slug,
        string $category,
        string $search,
        array $user,
    ): array {
        $this->requireWorkspace($slug, $user, 'can_manage');

        return $this->repository->searchDirectorySubjects($category, $search);
    }

    /**
     * HR: Kreira samo interni ili vanjski link-čvor; dokumente kreira Editor API.
     *
     * EN: Creates only an internal or external link node; documents are created by the Editor API.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function createLinkNode(string $slug, array $payload, array $user): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $nodeType = WorkspaceValue::string($payload['node_type'] ?? '');
        if (!in_array($nodeType, ['internal_link', 'external_link'], true)) {
            throw $this->invalid(
                __('Workspace API kreira samo linkove; dokument-stranice kreira Editor API.'),
            );
        }

        $node = $this->repository->saveNode(
            WorkspaceValue::int($workspace['id'] ?? 0),
            $payload,
            $this->userId($user),
        );
        $this->access->clearRequestCache();

        return $this->nodeDto($node);
    }

    /**
     * HR: Mijenja strukturne podatke čvora bez promjene dokumenta kojem pripada.
     *
     * EN: Updates structural node data without changing the linked document.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function updateNode(
        string $slug,
        int $nodeId,
        array $payload,
        array $user,
    ): array {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $node = $this->requireNode($workspace, $nodeId);
        $payload = $this->mergeNodePayload($node, $payload);
        $payload['id'] = $nodeId;

        $saved = $this->repository->saveNode(
            WorkspaceValue::int($workspace['id'] ?? 0),
            $payload,
            $this->userId($user),
        );
        $this->access->clearRequestCache();

        return $this->nodeDto($saved);
    }

    /**
     * HR: Briše link i podgranu; dokument-stranice namjerno prepušta Editor API-ju.
     *
     * EN: Deletes a link and its subtree; document pages are intentionally left to the Editor API.
     *
     * @param array<string,mixed> $user
     */
    public function deleteLinkNode(string $slug, int $nodeId, array $user): void
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $node = $this->requireNode($workspace, $nodeId);
        if (WorkspaceValue::string($node['node_type'] ?? '') === 'document') {
            throw $this->invalid(
                __('Dokument-stranicu briše Editor API kako bi sačuvao verzije i privitke.'),
            );
        }

        $this->repository->disableNodeTree(
            WorkspaceValue::int($workspace['id'] ?? 0),
            $nodeId,
            $this->userId($user),
        );
        $this->access->clearRequestCache();
    }

    /**
     * HR: Atomski sprema potpuni redoslijed i hijerarhiju stabla.
     *
     * EN: Atomically stores the complete tree order and hierarchy.
     *
     * @param list<array<string,mixed>> $placements
     * @param array<string,mixed> $user
     */
    public function reorderTree(string $slug, array $placements, array $user): void
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $this->repository->reorderNodes(
            WorkspaceValue::int($workspace['id'] ?? 0),
            $placements,
            $this->userId($user),
        );
        $this->access->clearRequestCache();
    }

    /**
     * HR: Vraća izravna ograničenja jednog čvora korisniku koji upravlja područjem.
     *
     * EN: Returns direct restrictions for one node to a user who manages the Workspace.
     *
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function getNodeAcl(string $slug, int $nodeId, array $user): array
    {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $this->requireNode($workspace, $nodeId);

        $workspaceSubjects = [];
        foreach (
            $this->repository->workspaceAclSubjects(
                WorkspaceValue::int($workspace['id'] ?? 0),
            ) as $subject
        ) {
            $key = WorkspaceValue::string($subject['subject_type'] ?? '')
            . ':'
            . WorkspaceValue::int($subject['subject_id'] ?? 0);
            $workspaceSubjects[$key] = $subject;
        }

        $subjects = [];
        foreach ($this->repository->nodeAclRows($nodeId) as $row) {
            $key = WorkspaceValue::string($row['subject_type'] ?? '')
            . ':'
            . WorkspaceValue::int($row['subject_id'] ?? 0);
            $subjects[] = $this->aclSubjectDto([
                ...($workspaceSubjects[$key] ?? []),
                ...$row,
            ]);
        }

        return $subjects;
    }

    /**
     * HR: Zamjenjuje izravna ograničenja čvora subjektima koji već pripadaju području.
     *
     * EN: Replaces direct node restrictions with subjects already assigned to the Workspace.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public function replaceNodeAcl(
        string $slug,
        int $nodeId,
        array $payload,
        array $user,
    ): array {
        $workspace = $this->requireWorkspace($slug, $user, 'can_manage');
        $workspaceId = WorkspaceValue::int($workspace['id'] ?? 0);
        $this->requireNode($workspace, $nodeId);
        $this->repository->replaceNodeAcl($workspaceId, $nodeId, $this->aclMap($payload));
        $this->access->clearRequestCache();

        return $this->getNodeAcl($slug, $nodeId, $user);
    }

    /**
     * HR: Učitava područje i skriva njegovo postojanje ako korisnik nema traženo pravo.
     *
     * EN: Loads a Workspace and conceals its existence when the user lacks the required permission.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function requireWorkspace(string $slug, array $user, string $permission): array
    {
        $workspace = $this->repository->findWorkspaceBySlug($slug);
        if (!is_array($workspace)) {
            throw $this->notFound(__('Područje nije pronađeno.'));
        }

        $permissions = $this->access->workspacePermissions($workspace, $user);
        if (!($permissions[$permission] ?? false)) {
            if ($permission === 'can_view') {
                throw $this->notFound(__('Područje nije pronađeno.'));
            }

            throw $this->forbidden(__('Nemate potrebno pravo nad područjem.'));
        }

        $workspace['permissions'] = $permissions;

        return $workspace;
    }

    /**
     * HR: Učitava čvor i potvrđuje da pripada zadanom području.
     *
     * EN: Loads a node and confirms that it belongs to the supplied Workspace.
     *
     * @param array<string,mixed> $workspace
     * @return array<string,mixed>
     */
    private function requireNode(array $workspace, int $nodeId): array
    {
        $node = $this->repository->findNodeById($nodeId);
        if (
            !is_array($node)
            || WorkspaceValue::int($node['workspace_id'] ?? 0)
                !== WorkspaceValue::int($workspace['id'] ?? 0)
        ) {
            throw $this->notFound(__('Stavka stabla nije pronađena.'));
        }

        return $node;
    }

    /**
     * HR: Zaštitne zapise obrisanih područja ograničava na administratora.
     *
     * EN: Restricts deleted-Workspace records to administrators.
     *
     * @param array<string,mixed> $user
     */
    private function requireAdministrator(array $user): void
    {
        if (!$this->access->isAdministrator($user)) {
            throw $this->forbidden(__('Operacija zahtijeva administratora.'));
        }
    }

    /**
     * HR: Vraća sigurni javni DTO područja bez internih delete i audit detalja.
     *
     * EN: Returns a safe public Workspace DTO without internal delete and audit details.
     *
     * @param array<string,mixed> $workspace
     * @return array<string,mixed>
     */
    private function workspaceDto(array $workspace): array
    {
        return [
            'id' => WorkspaceValue::int($workspace['id'] ?? 0),
            'uuid' => WorkspaceValue::string($workspace['uuid'] ?? ''),
            'slug' => WorkspaceValue::string($workspace['slug'] ?? ''),
            'name' => WorkspaceValue::string($workspace['name'] ?? ''),
            'description' => WorkspaceValue::string($workspace['description'] ?? ''),
            'visibility' => WorkspaceValue::string($workspace['visibility'] ?? 'restricted'),
            'owner_user_id' => WorkspaceValue::int($workspace['owner_user_id'] ?? 0),
            'is_archived' => (bool)($workspace['is_archived'] ?? false),
            'is_deleted' => (bool)($workspace['is_deleted'] ?? false),
            'created_at' => WorkspaceValue::string($workspace['created_at'] ?? ''),
            'updated_at' => WorkspaceValue::string($workspace['updated_at'] ?? ''),
            'deleted_at' => WorkspaceValue::string($workspace['deleted_at'] ?? ''),
            'permissions' => WorkspaceValue::stringKeyArray($workspace['permissions'] ?? []),
        ];
    }

    /**
     * HR: Dodaje efektivna prava području prije izgradnje javnog DTO odgovora.
     * EN: Adds effective permissions to a Workspace before building its public DTO response.
     *
     * @param array<string,mixed> $workspace
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function workspaceDtoWithPermissions(array $workspace, array $user): array
    {
        $workspace['permissions'] = $this->access->workspacePermissions($workspace, $user);

        return $this->workspaceDto($workspace);
    }

    /**
     * HR: Rekurzivno normalizira vidljivo stablo u stabilne javne DTO zapise.
     *
     * EN: Recursively normalizes the visible tree into stable public DTO records.
     *
     * @param list<array<string,mixed>> $nodes
     * @return list<array<string,mixed>>
     */
    private function treeDtos(array $nodes): array
    {
        $dtos = [];
        foreach ($nodes as $node) {
            $children = [];
            $rawChildren = $node['children'] ?? [];
            if (is_array($rawChildren)) {
                foreach ($rawChildren as $child) {
                    if (is_array($child)) {
                        $children[] = WorkspaceValue::stringKeyArray($child);
                    }
                }
            }

            $dto = $this->nodeDto($node);
            $dto['children'] = $this->treeDtos($children);
            $dtos[] = $dto;
        }

        return $dtos;
    }

    /**
     * HR: Normalizira jedan čvor bez izlaganja internih audit polja.
     *
     * EN: Normalizes one node without exposing internal audit fields.
     *
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    private function nodeDto(array $node): array
    {
        return [
            'id' => WorkspaceValue::int($node['id'] ?? 0),
            'uuid' => WorkspaceValue::string($node['uuid'] ?? ''),
            'parent_id' => ($parentId = WorkspaceValue::int($node['parent_id'] ?? 0)) > 0
                ? $parentId
                : null,
            'node_type' => WorkspaceValue::string($node['node_type'] ?? ''),
            'slug' => WorkspaceValue::string($node['slug'] ?? ''),
            'title' => WorkspaceValue::string($node['title'] ?? ''),
            'document_key' => WorkspaceValue::string($node['document_key'] ?? ''),
            'route_name' => WorkspaceValue::string($node['route_name'] ?? ''),
            'target_url' => WorkspaceValue::string($node['target_url'] ?? ''),
            'sort_order' => WorkspaceValue::int($node['sort_order'] ?? 0),
            'is_homepage' => (bool)($node['is_homepage'] ?? false),
            'permissions' => WorkspaceValue::stringKeyArray($node['permissions'] ?? []),
        ];
    }

    /**
     * HR: Normalizira ACL subjekt i njegov skup prava.
     *
     * EN: Normalizes an ACL subject and its permission set.
     *
     * @param array<string,mixed> $subject
     * @return array<string,mixed>
     */
    private function aclSubjectDto(array $subject): array
    {
        $permissions = [];
        foreach (self::PERMISSION_KEYS as $permission) {
            $permissions[$permission] = (bool)($subject[$permission] ?? false);
        }

        return [
            'type' => WorkspaceValue::string($subject['subject_type'] ?? ''),
            'id' => WorkspaceValue::int($subject['subject_id'] ?? 0),
            'label' => WorkspaceValue::string($subject['label'] ?? ''),
            'is_builtin' => (bool)($subject['is_builtin'] ?? false),
            'is_read_only' => (bool)($subject['is_read_only'] ?? false),
            'permissions' => $permissions,
        ];
    }

    /**
     * HR: Pretvara javnu listu ACL subjekata u format repozitorija.
     *
     * EN: Converts the public ACL subject list into the repository format.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function aclMap(array $payload): array
    {
        $subjects = $payload['subjects'] ?? null;
        if (!is_array($subjects) || !array_is_list($subjects)) {
            throw $this->invalid(__('Polje "subjects" mora biti JSON lista.'));
        }

        $acl = [];
        foreach ($subjects as $subject) {
            if (!is_array($subject)) {
                throw $this->invalid(__('Svaki ACL subjekt mora biti JSON objekt.'));
            }

            $type = WorkspaceValue::string($subject['type'] ?? '');
            $allowedTypes = [
                WorkspaceRepository::SUBJECT_USER,
                WorkspaceRepository::SUBJECT_GROUP,
                WorkspaceRepository::SUBJECT_PUBLIC,
                WorkspaceRepository::SUBJECT_AUTHENTICATED,
            ];
            if (!in_array($type, $allowedTypes, true)) {
                throw $this->invalid(__('ACL subjekt ima nepoznatu vrstu.'));
            }

            $builtInTypes = [
                WorkspaceRepository::SUBJECT_PUBLIC,
                WorkspaceRepository::SUBJECT_AUTHENTICATED,
            ];
            $subjectId = in_array($type, $builtInTypes, true)
            ? WorkspaceRepository::BUILT_IN_SUBJECT_ID
            : WorkspaceValue::int($subject['id'] ?? 0);
            if ($subjectId <= 0) {
                throw $this->invalid(__('ACL subjekt nema valjani ID.'));
            }

            $permissionsInput = WorkspaceValue::stringKeyArray($subject['permissions'] ?? []);
            $permissions = [];
            foreach (self::PERMISSION_KEYS as $permission) {
                $permissions[$permission] = (bool)($permissionsInput[$permission] ?? false);
            }

            $acl[$type][$subjectId] = $permissions;
        }

        return $acl;
    }

    /**
     * HR: Spaja izmjenjiva polja s postojećim područjem radi PATCH semantike.
     *
     * EN: Merges mutable fields with the existing Workspace for PATCH semantics.
     *
     * @param array<string,mixed> $workspace
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function mergeWorkspacePayload(array $workspace, array $payload): array
    {
        foreach (['name', 'slug', 'description', 'visibility', 'owner_user_id', 'is_archived'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $payload[$key] = $workspace[$key] ?? null;
            }
        }

        return $payload;
    }

    /**
     * HR: Spaja strukturna polja čvora i sprječava promjenu vrste ili dokumenta.
     *
     * EN: Merges structural node fields and prevents changing its type or document.
     *
     * @param array<string,mixed> $node
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function mergeNodePayload(array $node, array $payload): array
    {
        foreach (
            [
                'title',
                'slug',
                'parent_id',
                'sort_order',
                'is_homepage',
                'route_name',
                'target_url',
            ] as $key
        ) {
            if (!array_key_exists($key, $payload)) {
                $payload[$key] = $node[$key] ?? null;
            }
        }

        $payload['node_type'] = $node['node_type'] ?? '';
        $payload['document_key'] = $node['document_key'] ?? null;

        return $payload;
    }

    /**
     * HR: Vraća pozitivan ID autentificiranog korisnika ili zaustavlja operaciju.
     *
     * EN: Returns the authenticated user's positive ID or stops the operation.
     *
     * @param array<string,mixed> $user
     */
    private function userId(array $user): int
    {
        $userId = WorkspaceValue::int($user['id'] ?? 0);
        if ($userId <= 0) {
            throw $this->forbidden(__('API ključ nije povezan s aktivnim korisnikom.'));
        }

        return $userId;
    }

    /**
     * HR: Gradi stabilnu 403 domensku grešku.
     *
     * EN: Builds a stable 403 domain failure.
     */
    private function forbidden(string $message): WorkspaceApiException
    {
        return new WorkspaceApiException('workspace_access_denied', $message, 403);
    }

    /**
     * HR: Gradi stabilnu 404 domensku grešku.
     *
     * EN: Builds a stable 404 domain failure.
     */
    private function notFound(string $message): WorkspaceApiException
    {
        return new WorkspaceApiException('workspace_not_found', $message, 404);
    }

    /**
     * HR: Gradi stabilnu 422 domensku grešku za nevaljane podatke.
     *
     * EN: Builds a stable 422 domain failure for invalid input.
     */
    private function invalid(string $message): WorkspaceApiException
    {
        return new WorkspaceApiException('workspace_validation_failed', $message, 422);
    }
}
