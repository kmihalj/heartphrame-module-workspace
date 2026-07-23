<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use HeartPhrame\Routing\UrlGenerator;

use function is_array;

final readonly class WorkspaceEditorAccess
{
    /**
     * HR: Povezuje editorove akcije s područjem koje posjeduje dokument.
     * EN: Connects editor actions to the workspace that owns a document.
     */
    public function __construct(
        private WorkspaceRepository $repository,
        private WorkspaceAccessService $access,
        private WorkspaceConfig $config,
        private UrlGenerator $urlGenerator,
        private WorkspaceWorkflowService $workflow,
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
        return $this->access->canUseDocument($documentKey, 'can_view');
    }

    /**
     * HR: Provjerava nasljedno pravo uređivanja editor dokumenta.
     * EN: Checks inherited edit permission for an editor document.
     */
    public function canEditDocument(string $documentKey): bool
    {
        return $this->access->canUseDocument($documentKey, 'can_edit');
    }

    /**
     * HR: Provjerava zasebno nasljedno pravo objavljivanja editor dokumenta.
     * EN: Checks the separate inherited publishing permission for an editor document.
     */
    public function canPublishDocument(string $documentKey): bool
    {
        return $this->access->canUseDocument($documentKey, 'can_publish');
    }

    /**
     * HR: Provjerava nasljedno pravo brisanja editor dokumenta.
     * EN: Checks inherited delete permission for an editor document.
     */
    public function canDeleteDocument(string $documentKey): bool
    {
        return $this->access->canUseDocument($documentKey, 'can_delete');
    }

    /**
     * HR: Provjerava upravljačko pravo nad editor dokumentom.
     * EN: Checks management permission for an editor document.
     */
    public function canManageDocument(string $documentKey): bool
    {
        return $this->access->canUseDocument($documentKey, 'can_manage');
    }

    /**
     * HR: Vraća javnu Workspace putanju dokumenta umjesto samostalne editor slug rute.
     * EN: Returns the public Workspace path instead of the standalone editor slug route.
     */
    public function documentPath(string $documentKey, string $language = ''): string
    {
        $node = $this->repository->findNodeByDocumentKey($documentKey);
        if (!is_array($node)) {
            return '';
        }

        $workspace = $this->repository->findWorkspaceById(WorkspaceValue::int($node['workspace_id'] ?? 0));
        if (!is_array($workspace)) {
            return '';
        }

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

        return $path;
    }

    /**
     * HR: Vraća true kada dokument pripada aktivnom Workspace čvoru.
     * EN: Returns true when a document belongs to an active Workspace node.
     */
    public function ownsDocument(string $documentKey): bool
    {
        return is_array($this->repository->findNodeByDocumentKey($documentKey));
    }

    /**
     * HR: Vraća objavljenu verziju povezane jezične stranice; null ostavlja
     *     samostalni Editor prikaz netaknut, a nula skriva neobjavljeni sadržaj.
     * EN: Returns the published version for a linked page locale; null leaves
     *     standalone Editor rendering untouched, while zero hides unpublished content.
     */
    public function publicationVersion(string $documentKey, string $language): ?int
    {
        return $this->workflow->publicationVersion($documentKey, $language);
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
        $node = $this->repository->findNodeByDocumentKey($documentKey);
        if (!is_array($node) || !$this->canPublishDocument($documentKey)) {
            throw new \RuntimeException(__('Nemate pravo objavljivanja ove stranice.'));
        }

        $permissions = $this->access->nodePermissions(
            $this->workspaceForNode($node),
            $node,
        );
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
        $node = $this->repository->findNodeByDocumentKey($documentKey);
        if (!is_array($node) || !$this->canEditDocument($documentKey)) {
            throw new \RuntimeException(__('Nemate pravo uređivanja ove stranice.'));
        }

        $user = $this->access->currentUser();
        $this->workflow->discardDraft(
            WorkspaceValue::int($node['id'] ?? 0),
            $language,
            $currentVersionNumber,
            is_array($user) ? WorkspaceValue::int($user['id'] ?? 0) : 0,
        );
    }

    /**
     * HR: Učitava područje kojem pripada dokument-stranica ili prekida
     *     nevaljanu integracijsku operaciju.
     * EN: Loads the Workspace owning a document page or stops an invalid
     *     integration operation.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function workspaceForNode(array $node): array
    {
        $workspace = $this->repository->findWorkspaceById(
            WorkspaceValue::int($node['workspace_id'] ?? 0),
        );
        if (!is_array($workspace)) {
            throw new \RuntimeException(__('Područje nije pronađeno.'));
        }

        return $workspace;
    }
}
