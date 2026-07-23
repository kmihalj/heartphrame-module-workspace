<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleWorkspace\Controller\WorkspaceController;
use AaiEduHr\HeartPhrameModuleWorkspace\Controller\WorkspaceSettingsController;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceEditorAccess;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceEditorBridge;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceMenuIntegration;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceModuleViewRenderer;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceNotificationBridge;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceRouteRegistrar;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceSettingsService;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspaceWorkflowService;
use HeartPhrame\Alert\AlertHandler;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Bridge\ComposerBridge;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\Routes;
use HeartPhrame\Routing\UrlGenerator;
use HeartPhrame\View\View;
use Psr\Container\ContainerInterface;

return [
    WorkspaceConfig::class => static fn(ContainerInterface $container): WorkspaceConfig =>
        new WorkspaceConfig($container->get(ConfigInterface::class), dirname(__DIR__)),

    WorkspaceRepository::class => static fn(ContainerInterface $container): WorkspaceRepository =>
        new WorkspaceRepository($container->get(Database::class)),

    WorkspaceWorkflowService::class => static fn(ContainerInterface $container): WorkspaceWorkflowService =>
        new WorkspaceWorkflowService($container->get(WorkspaceRepository::class)),

    WorkspaceAccessService::class => static fn(ContainerInterface $container): WorkspaceAccessService =>
        new WorkspaceAccessService(
            $container->get(WorkspaceRepository::class),
            $container->get(AuthnHandlerInterface::class),
            $container->get(WorkspaceConfig::class),
            $container->get(WorkspaceWorkflowService::class),
        ),

    WorkspaceEditorBridge::class => static fn(ContainerInterface $container): WorkspaceEditorBridge =>
        new WorkspaceEditorBridge(
            $container,
            $container->get(ComposerBridge::class),
            $container->get(UrlGenerator::class),
        ),

    WorkspaceEditorAccess::class => static fn(ContainerInterface $container): WorkspaceEditorAccess =>
        new WorkspaceEditorAccess(
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceAccessService::class),
            $container->get(WorkspaceConfig::class),
            $container->get(UrlGenerator::class),
            $container->get(WorkspaceWorkflowService::class),
        ),

    WorkspaceNotificationBridge::class =>
        static fn(ContainerInterface $container): WorkspaceNotificationBridge =>
            new WorkspaceNotificationBridge(
                $container,
                $container->get(WorkspaceAccessService::class),
                $container->get(UrlGenerator::class),
            ),

    WorkspaceSettingsService::class => static fn(ContainerInterface $container): WorkspaceSettingsService =>
        new WorkspaceSettingsService(
            $container->get(WorkspaceConfig::class),
            $container->get(Routes::class),
        ),

    WorkspaceRouteRegistrar::class => static fn(ContainerInterface $container): WorkspaceRouteRegistrar =>
        new WorkspaceRouteRegistrar(
            $container->get(WorkspaceConfig::class),
            $container->get(Routes::class),
        ),

    WorkspaceMenuIntegration::class => static fn(ContainerInterface $container): WorkspaceMenuIntegration =>
        new WorkspaceMenuIntegration($container, $container->get(WorkspaceConfig::class)),

    WorkspaceModuleViewRenderer::class => static fn(ContainerInterface $container): WorkspaceModuleViewRenderer =>
        new WorkspaceModuleViewRenderer(
            $container->get(ResponseFactory::class),
            $container->get(ConfigInterface::class),
            $container->get(View::class),
        ),

    WorkspaceController::class => static fn(ContainerInterface $container): WorkspaceController =>
        new WorkspaceController(
            $container->get(ResponseFactory::class),
            $container->get(WorkspaceModuleViewRenderer::class),
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceAccessService::class),
            $container->get(WorkspaceEditorBridge::class),
            $container->get(WorkspaceConfig::class),
            $container->get(UrlGenerator::class),
            $container->get(AlertHandler::class),
            $container->get(WorkspaceWorkflowService::class),
            $container->get(WorkspaceNotificationBridge::class),
        ),

    WorkspaceSettingsController::class => static fn(ContainerInterface $container): WorkspaceSettingsController =>
        new WorkspaceSettingsController(
            $container->get(ResponseFactory::class),
            $container->get(WorkspaceModuleViewRenderer::class),
            $container->get(WorkspaceRepository::class),
            $container->get(WorkspaceAccessService::class),
            $container->get(WorkspaceSettingsService::class),
            $container->get(WorkspaceConfig::class),
            $container->get(UrlGenerator::class),
            $container->get(AlertHandler::class),
        ),
];
