<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use HeartPhrame\Routing\UrlGenerator;

use function array_key_exists;
use function is_array;

final class WorkspaceEditorAccess
{
    /**
     * HR: Pamti dokument i njegovo područje tijekom jednog zahtjeva jer Editor
     *     gradi više poveznica i akcija za isti dokument.
     * EN: Caches a document and its workspace for one request because the Editor
     *     builds several links and actions for the same document.
     *
     * @var array<string, array{
     *     node: array<string, mixed>,
     *     workspace: array<string, mixed>
     * }|null>
     */
    private array $documentContextCache = [];

    /**
     * HR: Pamti efektivna prava po dokumentu i korisniku tijekom jednog zahtjeva.
     * EN: Caches effective permissions per document and user for one request.
     *
     * @var array<string, array<string, bool>>
     */
    private array $documentPermissionCache = [];

    /**
     * HR: Pamti javne putanje po dokumentu i jeziku tijekom jednog zahtjeva.
     * EN: Caches public paths per document and language for one request.
     *
     * @var array<string, string>
     */
    private array $documentPathCache = [];

    /**
     * HR: Pamti broj objavljene verzije po dokumentu i jeziku tijekom jednog zahtjeva.
     * EN: Caches the published version number per document and language for one request.
     *
     * @var array<string, int>
     */
    private array $publicationVersionCache = [];

    /**
     * HR: Povezuje editorove akcije s područjem koje posjeduje dokument.
     * EN: Connects editor actions to the workspace that owns a document.
     */
    public function __construct(
        private readonly WorkspaceRepository $repository,
        private readonly WorkspaceAccessService $access,
        private readonly WorkspaceConfig $config,
        private readonly UrlGenerator $urlGenerator,
        private readonly WorkspaceWorkflowService $workflow,
    ) {
    }

    /**
     * HR: Provjerava smije li korisnik kreirati dokument unutar zadanog područja.
     * EN: Checks whether a user may create a document inside the given workspace.
     */
    public function canCreateDocument(string $workspaceSlug): bool
    {
        $workspace = $this->repository->findWorkspaceBySlug($workspaceSlug);
        if (!is_array($workspace)) {
            return false;
        }

        return $this->access->workspacePermissions($workspace)['can_add'];
    }

    /**
     * HR: Provjerava nasljedno pravo čitanja editor dokumenta.
     * EN: Checks inherited read permission for an editor document.
     */
    public function canReadDocument(string $documentKey): bool
    {
        return (bool)($this->documentPermissions($documentKey)['can_view'] ?? false);
    }

    /**
     * HR: Provjerava nasljedno pravo uređivanja editor dokumenta.
     * EN: Checks inherited edit permission for an editor document.
     */
    public function canEditDocument(string $documentKey): bool
    {
        return (bool)($this->documentPermissions($documentKey)['can_edit'] ?? false);
    }

    /**
     * HR: Provjerava zasebno nasljedno pravo objavljivanja editor dokumenta.
     * EN: Checks the separate inherited publishing permission for an editor document.
     */
    public function canPublishDocument(string $documentKey): bool
    {
        return (bool)($this->documentPermissions($documentKey)['can_publish'] ?? false);
    }

    /**
     * HR: Provjerava nasljedno pravo brisanja editor dokumenta.
     * EN: Checks inherited delete permission for an editor document.
     */
    public function canDeleteDocument(string $documentKey): bool
    {
        return (bool)($this->documentPermissions($documentKey)['can_delete'] ?? false);
    }

    /**
     * HR: Provjerava upravljačko pravo nad editor dokumentom.
     * EN: Checks management permission for an editor document.
     */
    public function canManageDocument(string $documentKey): bool
    {
        return (bool)($this->documentPermissions($documentKey)['can_manage'] ?? false);
    }

    /**
     * HR: Vraća javnu Workspace putanju dokumenta umjesto samostalne editor slug rute.
     * EN: Returns the public Workspace path instead of the standalone editor slug route.
     */
    public function documentPath(string $documentKey, string $language = ''): string
    {
        $cacheKey = $documentKey . '|' . $language;
        if (array_key_exists($cacheKey, $this->documentPathCache)) {
            return $this->documentPathCache[$cacheKey];
        }

        $context = $this->documentContext($documentKey);
        if (!is_array($context)) {
            return '';
        }

        $node = $context['node'];
        $workspace = $context['workspace'];
        $workspaceSlug = WorkspaceValue::string($workspace['slug'] ?? '');
        $nodeSlug = WorkspaceValue::string($node['slug'] ?? '');
        $path = $this->urlGenerator->namedRouteExists('workspace.node.show')
        ? $this->urlGenerator->getPathFor('workspace.node.show', [
            'workspaceSlug' => $workspaceSlug,
            'nodeSlug' => $nodeSlug,
        ])
        : rtrim($this->urlGenerator->getBasePath(), '/')
        . '/'
        . trim($this->config->rootPath(), '/')
        . '/'
        . rawurlencode($workspaceSlug)
        . '/'
        . rawurlencode($nodeSlug);
        if ($language !== '') {
            $path .= '?lang=' . rawurlencode($language);
        }

        return $this->documentPathCache[$cacheKey] = $path;
    }

    /**
     * HR: Vraća true kada dokument pripada aktivnom Workspace čvoru.
     * EN: Returns true when a document belongs to an active Workspace node.
     */
    public function ownsDocument(string $documentKey): bool
    {
        return is_array($this->documentContext($documentKey));
    }

    /**
     * HR: Vraća objavljenu verziju povezane jezične stranice; null ostavlja
     *     samostalni Editor prikaz netaknut, a nula skriva neobjavljeni sadržaj.
     * EN: Returns the published version for a linked page locale; null leaves
     *     standalone Editor rendering untouched, while zero hides unpublished content.
     */
    public function publicationVersion(string $documentKey, string $language): ?int
    {
        $context = $this->documentContext($documentKey);
        if (!is_array($context)) {
            return null;
        }

        $cacheKey = $documentKey . '|' . $language;
        if (array_key_exists($cacheKey, $this->publicationVersionCache)) {
            return $this->publicationVersionCache[$cacheKey];
        }

        return $this->publicationVersionCache[$cacheKey] = $this->workflow->publicationVersionForNode(
            WorkspaceValue::int($context['node']['id'] ?? 0),
            $language,
        );
    }

    /**
     * HR: Nakon Editor spremanja označava povezanu stranicu nacrtom i bilježi
     *     broj upravo nastale nepromjenjive verzije.
     * EN: After an Editor save, marks the linked page as draft and records the
     *     newly created immutable version number.
     */
    public function markDocumentDraft(
        string $documentKey,
        string $language,
        int $versionNumber,
    ): void {
        $user = $this->access->currentUser();
        $this->workflow->markDocumentDraft(
            $documentKey,
            $language,
            $versionNumber,
            is_array($user) ? WorkspaceValue::int($user['id'] ?? 0) : 0,
        );
    }

    /**
     * HR: Nakon Editorove objave usklađuje Workspace workflow uz zasebnu
     *     provjeru prava objavljivanja.
     * EN: Synchronizes the Workspace workflow after an Editor publication while
     *     independently enforcing the publishing permission.
     */
    public function publishDocumentDraft(
        string $documentKey,
        string $language,
        int $versionNumber,
    ): void {
        $context = $this->documentContext($documentKey);
        if (!is_array($context) || !$this->canPublishDocument($documentKey)) {
            throw new \RuntimeException(__('Nemate pravo objavljivanja ove stranice.'));
        }

        $node = $context['node'];
        $permissions = $this->documentPermissions($documentKey);
        $user = $this->access->currentUser();
        $this->workflow->transition(
            WorkspaceValue::int($node['id'] ?? 0),
            $language,
            'publish',
            $versionNumber,
            is_array($user) ? WorkspaceValue::int($user['id'] ?? 0) : 0,
            (bool)($permissions['can_edit'] ?? false),
            (bool)($permissions['can_publish'] ?? false),
            (bool)($permissions['can_manage'] ?? false),
        );
        unset($this->publicationVersionCache[$documentKey . '|' . $language]);
    }

    /**
     * HR: Nakon Editorova odbacivanja nacrta vraća workflow na zadnju objavu
     *     ili na čisti početni nacrt nove stranice.
     * EN: After Editor draft discard, returns the workflow to the last
     *     publication or to a clean initial draft for a new page.
     */
    public function discardDocumentDraft(
        string $documentKey,
        string $language,
        int $currentVersionNumber,
    ): void {
        $context = $this->documentContext($documentKey);
        if (!is_array($context) || !$this->canEditDocument($documentKey)) {
            throw new \RuntimeException(__('Nemate pravo uređivanja ove stranice.'));
        }

        $node = $context['node'];
        $user = $this->access->currentUser();
        $this->workflow->discardDraft(
            WorkspaceValue::int($node['id'] ?? 0),
            $language,
            $currentVersionNumber,
            is_array($user) ? WorkspaceValue::int($user['id'] ?? 0) : 0,
        );
        unset($this->publicationVersionCache[$documentKey . '|' . $language]);
    }

    /**
     * HR: Učitava dokument i pripadajuće područje samo jednom tijekom zahtjeva.
     *     Null se također pamti kako nepostojeći dokument ne bi stalno tražili.
     * EN: Loads a document and its workspace only once during a request. Null is
     *     cached as well so a missing document is not looked up repeatedly.
     *
     * @return array{
     *     node: array<string, mixed>,
     *     workspace: array<string, mixed>
     * }|null
     */
    private function documentContext(string $documentKey): ?array
    {
        if (array_key_exists($documentKey, $this->documentContextCache)) {
            return $this->documentContextCache[$documentKey];
        }

        $node = $this->repository->findNodeByDocumentKey($documentKey);
        if (!is_array($node)) {
            return $this->documentContextCache[$documentKey] = null;
        }

        $workspace = $this->repository->findWorkspaceById(
            WorkspaceValue::int($node['workspace_id'] ?? 0),
        );
        if (!is_array($workspace)) {
            return $this->documentContextCache[$documentKey] = null;
        }

        return $this->documentContextCache[$documentKey] = [
            'node' => $node,
            'workspace' => $workspace,
        ];
    }

    /**
     * HR: Računa i pamti cijeli skup prava kako pojedinačne Editor provjere
     *     čitanja, izmjene, objave i brisanja ne ponavljaju isti ACL izračun.
     * EN: Calculates and caches the complete permission set so individual Editor
     *     read, edit, publish, and delete checks do not repeat the same ACL work.
     *
     * @return array<string, bool>
     */
    private function documentPermissions(string $documentKey): array
    {
        $user = $this->access->currentUser();
        $cacheKey = $documentKey
        . '|'
        . WorkspaceValue::int(is_array($user) ? ($user['id'] ?? 0) : 0)
        . '|'
        . (int)$this->access->isAdministrator($user);
        if (array_key_exists($cacheKey, $this->documentPermissionCache)) {
            return $this->documentPermissionCache[$cacheKey];
        }

        $context = $this->documentContext($documentKey);
        if (!is_array($context)) {
            return $this->documentPermissionCache[$cacheKey] = [];
        }

        return $this->documentPermissionCache[$cacheKey] = $this->access->nodePermissions(
            $context['workspace'],
            $context['node'],
            $user,
        );
    }
}
