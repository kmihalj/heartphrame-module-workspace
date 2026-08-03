<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use RuntimeException;

use function array_values;
use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function strtolower;
use function trim;

/**
 * HR: Određuje naslovnicu aplikacije prema javnoj, prijavljenoj i osobnoj
 * politici te prije svakog redirecta ponovno provjerava ACL i objavljenost.
 * EN: Resolves the application homepage from public, authenticated, and
 * personal policy while rechecking ACL and publication before every redirect.
 */
final readonly class WorkspaceHomepageService
{
    private const GENERIC_AUTHENTICATED_USER_ID = 2147483647;

    /**
     * HR: Prima Workspace podatke, ACL, workflow i URL servise bez obrnute ovisnosti Autha.
     * EN: Receives Workspace data, ACL, workflow, and URL services without an Auth reverse dependency.
     */
    public function __construct(
        private WorkspaceRepository $repository,
        private WorkspaceHomepageRepository $homepages,
        private WorkspaceAccessService $access,
        private WorkspaceWorkflowService $workflow,
        private WorkspaceConfig $workspaceConfig,
        private UrlGenerator $urlGenerator,
        private TranslatorInterface $translator,
        private ConfigInterface $config,
    ) {
    }

    /**
     * HR: Provjerava je li potpuna Workspace shema spremna.
     * EN: Checks whether the complete Workspace schema is ready.
     */
    public function tablesReady(): bool
    {
        return $this->repository->tablesReady() && $this->homepages->tablesReady();
    }

    /**
     * HR: Priprema administratorske vrijednosti i odvojene sigurne izbore za goste i prijavljene.
     * EN: Prepares administrator values and separate safe choices for guests and authenticated users.
     *
     * @return array<string, mixed>
     */
    public function settingsForForm(): array
    {
        $settings = $this->homepages->settings();

        return [
            'settings' => $settings,
            'public_option_groups' => $this->selectablePageGroups(null),
            'authenticated_option_groups' => $this->selectablePageGroups([
                'id' => self::GENERIC_AUTHENTICATED_USER_ID,
                'is_admin' => false,
            ]),
        ];
    }

    /**
     * HR: Validira i sprema administratorsku politiku naslovnice.
     * EN: Validates and stores the administrator homepage policy.
     *
     * @param array<string, mixed> $input
     */
    public function saveSettings(array $input, int $actorUserId): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Migracija naslovnice područja nije primijenjena.'));
        }

        $publicNodeId = WorkspaceValue::int($input['public_node_id'] ?? 0);
        $authenticatedNodeId = WorkspaceValue::int($input['authenticated_node_id'] ?? 0);
        if ($publicNodeId > 0 && !$this->groupsContainNode($this->selectablePageGroups(null), $publicNodeId)) {
            throw new RuntimeException(__('Javna naslovnica mora biti objavljena i dostupna gostima.'));
        }

        $genericUser = ['id' => self::GENERIC_AUTHENTICATED_USER_ID, 'is_admin' => false];
        if (
            $authenticatedNodeId > 0
            && !$this->groupsContainNode($this->selectablePageGroups($genericUser), $authenticatedNodeId)
        ) {
            throw new RuntimeException(
                __('Naslovnica za prijavljene mora biti objavljena i dostupna svim prijavljenim korisnicima.'),
            );
        }

        $this->homepages->saveSettings(
            $publicNodeId,
            $authenticatedNodeId,
            $this->boolValue($input['allow_user_selection'] ?? false),
            $actorUserId,
        );
    }

    /**
     * HR: Priprema osobni odabir samo za trenutačnog prijavljenog korisnika.
     * EN: Prepares personal selection only for the current authenticated user.
     *
     * @return array<string, mixed>|null
     */
    public function accountData(int $userId): ?array
    {
        if (!$this->tablesReady() || $userId <= 0) {
            return null;
        }

        $currentUser = $this->access->currentUser();
        if ($this->userId($currentUser) !== $userId) {
            return null;
        }

        $settings = $this->homepages->settings();
        if (!$settings['allow_user_selection']) {
            return null;
        }

        $groups = $this->selectablePageGroups($currentUser);
        $selectedNodeId = $this->homepages->userNodeId($userId);
        $selectionUnavailable = $selectedNodeId > 0 && !$this->groupsContainNode($groups, $selectedNodeId);

        return [
            'selectedNodeId' => $selectionUnavailable ? 0 : $selectedNodeId,
            'selectionUnavailable' => $selectionUnavailable,
            'optionGroups' => $groups,
        ];
    }

    /**
     * HR: Sprema osobni odabir samo ako ga korisnik trenutno smije otvoriti.
     * EN: Stores a personal selection only when the user may currently open it.
     */
    public function saveUserSelection(int $userId, int $nodeId): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Migracija naslovnice područja nije primijenjena.'));
        }

        $currentUser = $this->access->currentUser();
        if ($userId <= 0 || $this->userId($currentUser) !== $userId) {
            throw new RuntimeException(__('Za osobnu naslovnicu potrebna je prijava.'));
        }

        if (!$this->homepages->settings()['allow_user_selection']) {
            throw new RuntimeException(__('Osobni odabir naslovnice nije omogućen.'));
        }

        if ($nodeId > 0 && !$this->groupsContainNode($this->selectablePageGroups($currentUser), $nodeId)) {
            throw new RuntimeException(__('Odabrana stranica nije objavljena ili joj nemate pristup.'));
        }

        $this->homepages->saveUserNodeId($userId, $nodeId);
    }

    /**
     * HR: Vraća kanonsku Workspace putanju prema osobnom, prijavljenom i javnom prioritetu.
     * EN: Returns the canonical Workspace path using personal, authenticated, and public precedence.
     */
    public function resolvePath(): ?string
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $settings = $this->homepages->settings();
        $user = $this->access->currentUser();
        $userId = $this->userId($user);
        $candidateNodeIds = [];
        if ($userId > 0 && $settings['allow_user_selection']) {
            $candidateNodeIds[] = $this->homepages->userNodeId($userId);
        }

        if ($userId > 0) {
            $candidateNodeIds[] = $settings['authenticated_node_id'];
        }

        $candidateNodeIds[] = $settings['public_node_id'];
        foreach (array_values(array_unique($candidateNodeIds)) as $nodeId) {
            if ($nodeId <= 0) {
                continue;
            }

            $path = $this->targetPath($nodeId, $user);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * HR: Gradi grupirane opcije objavljenih dokument-stranica koje publika smije vidjeti.
     * EN: Builds grouped options of published document pages visible to the audience.
     *
     * @param array<string, mixed>|null $user
     * @return list<array{name:string,options:list<array{id:int,title:string}>}>
     */
    private function selectablePageGroups(?array $user): array
    {
        if (!$this->tablesReady()) {
            return [];
        }

        $groups = [];
        foreach ($this->repository->activeWorkspaces() as $workspace) {
            if (!$this->access->workspacePermissions($workspace, $user)['can_view']) {
                continue;
            }

            $nodes = $this->repository->nodesForWorkspace(WorkspaceValue::int($workspace['id'] ?? 0));
            $permissions = $this->access->nodePermissionsForNodes($workspace, $nodes, $user);
            $documentNodeIds = [];
            foreach ($nodes as $node) {
                if (WorkspaceValue::string($node['node_type'] ?? '') === 'document') {
                    $documentNodeIds[] = WorkspaceValue::int($node['id'] ?? 0);
                }
            }

            $workflows = $this->repository->nodeWorkflowsForNodesAllLanguages($documentNodeIds);
            $options = [];
            foreach ($nodes as $node) {
                $nodeId = WorkspaceValue::int($node['id'] ?? 0);
                if (WorkspaceValue::string($node['node_type'] ?? '') !== 'document') {
                    continue;
                }

                if (!(bool)($permissions[$nodeId]['can_view'] ?? false)) {
                    continue;
                }

                if ($this->readableLanguage($workflows[$nodeId] ?? []) === '') {
                    continue;
                }

                $options[] = [
                    'id' => $nodeId,
                    'title' => WorkspaceValue::string($node['title'] ?? ''),
                ];
            }

            if ($options !== []) {
                $groups[] = [
                    'name' => WorkspaceValue::string($workspace['name'] ?? ''),
                    'options' => $options,
                ];
            }
        }

        return $groups;
    }

    /**
     * HR: Provjerava čvor, područje, ACL i objavljeni jezik prije gradnje internog URL-a.
     * EN: Validates the node, workspace, ACL, and published locale before building an internal URL.
     *
     * @param array<string, mixed>|null $user
     */
    private function targetPath(int $nodeId, ?array $user): ?string
    {
        $node = $this->repository->findNodeById($nodeId);
        if (!is_array($node) || WorkspaceValue::string($node['node_type'] ?? '') !== 'document') {
            return null;
        }

        $workspace = $this->repository->findWorkspaceById(WorkspaceValue::int($node['workspace_id'] ?? 0));
        if (
            !is_array($workspace)
            || !$this->access->workspacePermissions($workspace, $user)['can_view']
            || !$this->access->nodePermissions($workspace, $node, $user)['can_view']
        ) {
            return null;
        }

        $workflows = $this->repository->nodeWorkflowsForNodesAllLanguages([$nodeId]);
        $language = $this->readableLanguage($workflows[$nodeId] ?? []);
        if ($language === '') {
            return null;
        }

        $workspaceSlug = WorkspaceValue::string($workspace['slug'] ?? '');
        $nodeSlug = WorkspaceValue::string($node['slug'] ?? '');
        if ($workspaceSlug === '' || $nodeSlug === '') {
            return null;
        }

        if ($this->urlGenerator->namedRouteExists('workspace.node.show')) {
            $path = $this->urlGenerator->getPathFor('workspace.node.show', [
                'workspaceSlug' => $workspaceSlug,
                'nodeSlug' => $nodeSlug,
            ]);
        } else {
            $path = rtrim($this->urlGenerator->getBasePath(), '/')
            . '/'
            . trim($this->workspaceConfig->rootPath(), '/')
            . '/'
            . rawurlencode($workspaceSlug)
            . '/'
            . rawurlencode($nodeSlug);
        }

        return $path . '?lang=' . rawurlencode($language);
    }

    /**
     * HR: Bira aktualni, fallback ili prvi objavljeni jezik stranice.
     * EN: Selects the current, fallback, or first published page locale.
     *
     * @param list<array<string, mixed>> $workflows
     */
    private function readableLanguage(array $workflows): string
    {
        $readable = [];
        foreach ($workflows as $workflow) {
            if (!$this->workflow->isReadableWorkflow($workflow)) {
                continue;
            }

            $language = $this->normalizeLanguage(WorkspaceValue::string($workflow['language_code'] ?? ''));
            if ($language !== '') {
                $readable[$language] = true;
            }
        }

        foreach ($this->languagePreference() as $language) {
            if (isset($readable[$language])) {
                return $language;
            }
        }

        return WorkspaceValue::string(array_key_first($readable));
    }

    /**
     * HR: Vraća jezični prioritet aplikacije bez pretpostavke da su jezici HR i EN.
     * EN: Returns the application locale priority without assuming HR and EN locales.
     *
     * @return list<string>
     */
    private function languagePreference(): array
    {
        $languages = [
            $this->normalizeLanguage($this->translator->getLocale()),
            $this->normalizeLanguage($this->config->getAsString('app.localization.fallback_locale', 'en') ?? 'en'),
        ];
        $supportedLocales = $this->config->getAsArrayWithValuesAsNonEmptyStrings(
            'app.localization.supported_locales',
        ) ?? [];
        foreach ($supportedLocales as $language) {
            $languages[] = $this->normalizeLanguage($language);
        }

        return array_values(array_unique(array_filter($languages)));
    }

    /**
     * HR: Provjerava postoji li ID u grupiranim opcijama forme.
     * EN: Checks whether an ID exists in grouped form options.
     *
     * @param list<array{name:string,options:list<array{id:int,title:string}>}> $groups
     */
    private function groupsContainNode(array $groups, int $nodeId): bool
    {
        foreach ($groups as $group) {
            foreach ($group['options'] as $option) {
                if ($option['id'] === $nodeId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * HR: Čita ID iz normaliziranog Auth session payloada.
     * EN: Reads the ID from a normalized Auth session payload.
     *
     * @param array<string, mixed>|null $user
     */
    private function userId(?array $user): int
    {
        return is_array($user) && is_numeric($user['id'] ?? null) ? (int)$user['id'] : 0;
    }

    /**
     * HR: Normalizira checkbox vrijednost.
     * EN: Normalizes a checkbox value.
     */
    private function boolValue(mixed $value): bool
    {
        return is_scalar($value)
        && in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * HR: Prihvaća samo kratke BCP-47 oznake jezika koje Workspace ruta razumije.
     * EN: Accepts only short BCP-47 locale tags understood by the Workspace route.
     */
    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) === 1 ? $language : '';
    }
}
