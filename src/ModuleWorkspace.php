<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace;

/**
 * @see \AaiEduHr\HeartPhrameModuleWorkspace\Tests\ModuleWorkspaceTest
 */
final class ModuleWorkspace
{
    public const PACKAGE_NAME = 'aaieduhr/heartphrame-module-workspace';

    public const TABLE_WORKSPACES = 'workspaces';

    public const TABLE_WORKSPACE_ACL = 'workspace_acl';

    public const TABLE_WORKSPACE_NODES = 'workspace_nodes';

    public const TABLE_WORKSPACE_NODE_ACL = 'workspace_node_acl';

    public const TABLE_WORKSPACE_NODE_WORKFLOWS = 'workspace_node_workflows';

    /**
     * HR: Sprječava instanciranje klase koja služi samo kao registar stabilnog naziva paketa.
     * EN: Prevents instantiation of a class used only as a registry for the stable package name.
     */
    private function __construct()
    {
    }
}
