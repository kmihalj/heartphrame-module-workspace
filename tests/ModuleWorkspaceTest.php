<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleWorkspace::class)]
final class ModuleWorkspaceTest extends TestCase
{
    /**
     * HR: Provjerava stabilni Composer identitet koji koriste manifest i host aplikacija.
     * EN: Verifies the stable Composer identity used by the manifest and host application.
     */
    public function testPackageNameIsStable(): void
    {
        $this->assertSame('aaieduhr/heartphrame-module-workspace', ModuleWorkspace::PACKAGE_NAME);
    }
}
