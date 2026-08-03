<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Controller;

use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceModuleViewRenderer;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceShortsService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceValue;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_array;
use function is_scalar;
use function preg_match;
use function strtolower;
use function trim;

/**
 * HR: Poslužuje javnu, ACL-filtriranu stranicu Sažetaka jednog područja.
 * EN: Serves one Workspace's public, ACL-filtered Shorts page.
 */
final readonly class WorkspaceShortsController
{
    /**
     * HR: Prima repozitorij, ACL, poslovni servis i zajedničke view/route servise.
     * EN: Receives repository, ACL, business logic, and shared view/route services.
     */
    public function __construct(
        private WorkspaceModuleViewRenderer $viewRenderer,
        private WorkspaceRepository $repository,
        private WorkspaceAccessService $access,
        private WorkspaceShortsService $shorts,
        private WorkspaceConfig $config,
        private UrlGenerator $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * HR: Prikazuje samo stranice koje korisnik smije vidjeti i koje imaju objavu.
     * EN: Displays only pages the current user may view and that have a publication.
     */
    public function index(ServerRequestInterface $request, string $workspaceSlug): ResponseInterface
    {
        if (!$this->repository->tablesReady()) {
            return $this->viewRenderer->render('workspace/access-denied', [
                'title' => __('Područja još nisu instalirana'),
                'message' => __('Pokrenite početnu Workspace migraciju pa ponovno otvorite stranicu.'),
                'indexPath' => $this->pathFor('workspace.index', '/workspaces'),
            ], true, 503);
        }

        $workspace = $this->repository->findWorkspaceBySlug($workspaceSlug);
        if (!is_array($workspace)) {
            return $this->notFound();
        }

        $permissions = $this->access->workspacePermissions($workspace);
        if (!(bool)($permissions['can_view'] ?? false)) {
            return $this->accessDenied();
        }

        $language = $this->language($request);
        $query = $request->getQueryParams();
        $model = $this->shorts->viewModel(
            $workspace,
            $language,
            $query,
            $this->config->siteDefaultLanguage(),
        );
        $treeVisible = $this->queryVisibility(
            $query['tree'] ?? null,
            $this->config->treeVisibleByDefault(),
        );
        $displayOptionsVisible = $this->queryVisibility(
            $query['options'] ?? null,
            $this->config->shortsDisplayOptionsVisibleByDefault(),
        );

        return $this->viewRenderer->render('workspace/shorts', [
            'title' => __('Sažetci') . ' · ' . WorkspaceValue::string($workspace['name'] ?? ''),
            'themeTitleContext' => 'integrated',
            'workspace' => $workspace,
            'tree' => $model['tree'],
            'articles' => $model['articles'],
            'depth' => $model['depth'],
            'limit' => $model['limit'],
            'order' => $model['order'],
            'total' => $model['total'],
            'allAvailable' => $model['all_available'],
            'language' => $language,
            'shortsPath' => $model['shorts_path'],
            'treeVisibleByDefault' => $treeVisible,
            'displayOptionsVisibleByDefault' => $displayOptionsVisible,
            'assetsCssPath' => $this->pathFor('workspace.assets.css', '/workspaces/assets.css'),
        ]);
    }

    /**
     * HR: Čita odabrani ili aktivni jezik uz sigurni fallback na zadani jezik sitea.
     * EN: Reads the selected or active locale with a safe site-default fallback.
     */
    private function language(ServerRequestInterface $request): string
    {
        $query = $request->getQueryParams();
        $language = strtolower(WorkspaceValue::string(
            $query['lang'] ?? $this->translator->getLocale(),
        ));

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) === 1
        ? $language
        : $this->config->siteDefaultLanguage();
    }

    /**
     * HR: Čita `1/0`, `true/false`, `yes/no` i `on/off` bez dvosmislenog PHP castanja.
     * EN: Reads `1/0`, `true/false`, `yes/no`, and `on/off` without ambiguous PHP casting.
     */
    private function queryVisibility(mixed $value, bool $fallback): bool
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return in_array($normalized, ['0', 'false', 'no', 'off'], true) ? false : $fallback;
    }

    /**
     * HR: Vraća 403 bez izlaganja sadržaja nedostupnog Područja.
     * EN: Returns 403 without exposing content from an inaccessible Workspace.
     */
    private function accessDenied(): ResponseInterface
    {
        return $this->viewRenderer->render('workspace/access-denied', [
            'title' => __('Nedozvoljen pristup'),
            'message' => __('Nemate pravo pregledavati ovo područje.'),
            'indexPath' => $this->pathFor('workspace.index', '/workspaces'),
        ], true, 403);
    }

    /**
     * HR: Vraća 404 za nepostojeći ili uklonjeni javni slug Područja.
     * EN: Returns 404 for a missing or removed public Workspace slug.
     */
    private function notFound(): ResponseInterface
    {
        return $this->viewRenderer->render('workspace/access-denied', [
            'title' => __('Područje nije pronađeno'),
            'message' => __('Traženo područje ne postoji ili više nije aktivno.'),
            'indexPath' => $this->pathFor('workspace.index', '/workspaces'),
        ], true, 404);
    }

    /**
     * HR: Generira named rutu ili stabilni fallback za nepotpunu instalaciju.
     * EN: Generates a named route or stable fallback for an incomplete installation.
     */
    private function pathFor(string $routeName, string $fallback): string
    {
        return $this->urlGenerator->namedRouteExists($routeName)
        ? $this->urlGenerator->getPathFor($routeName)
        : $fallback;
    }
}
