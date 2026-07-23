<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleAuth\Middleware\RequireAuthenticatedUserMiddleware;
use AaiEduHr\HeartPhrameModuleWorkspace\Controller\WorkspaceController;
use AaiEduHr\HeartPhrameModuleWorkspace\Controller\WorkspaceSettingsController;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WorkspaceManifestTest extends TestCase
{
    /**
     * HR: Provjerava javni ugovor početne rute bez pokretanja cijele aplikacije.
     * EN: Verifies the initial route contract without booting the complete application.
     */
    public function testManifestRegistersWorkspaceRouteContract(): void
    {
        $manifest = require dirname(__DIR__) . '/heartphrame-manifest.php';
        $routes = $manifest->getBaseRoutes();

        $routesByName = [];
        foreach ($routes as $route) {
            $routesByName[$route[3]] = $route;
        }

        $this->assertCount(20, $routesByName);
        $this->assertSame(
            ['GET', '/workspaces', WorkspaceController::class . '@index', 'workspace.index', []],
            $routesByName['workspace.index'],
        );
        $this->assertSame(
            WorkspaceSettingsController::class . '@index',
            $routesByName['workspace.settings'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.manage'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@searchAclSubjects',
            $routesByName['workspace.acl.subjects'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.acl.subjects'][4],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.node.save'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@nodeDialog',
            $routesByName['workspace.node.dialog'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.node.dialog'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@transitionWorkflow',
            $routesByName['workspace.workflow.transition'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.workflow.transition'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@saveTreeOrder',
            $routesByName['workspace.tree.order.save'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.tree.order.save'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@createPage',
            $routesByName['workspace.page.create'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.page.create'][4],
        );
        $this->assertSame(
            WorkspaceController::class . '@scripts',
            $routesByName['workspace.assets.js'][2],
        );
        $this->assertContains(
            RequireAuthenticatedUserMiddleware::class,
            $routesByName['workspace.settings'][4],
        );
    }
}
