<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Tests;

use AaiEduHr\HeartPhrameModuleWorkspace\Contract\WorkspacePresentationProviderInterface;
use AaiEduHr\HeartPhrameModuleWorkspace\Service\WorkspacePresentationRegistry;
use HeartPhrame\Localization\TranslatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspacePresentationRegistry::class)]
final class WorkspacePresentationRegistryTest extends TestCase
{
    /** HR: Registry grupno primjenjuje provider i koristi aktivni jezik. EN: The registry applies a provider in a batch using the active locale. */
    public function testRegisteredProviderReceivesActiveLocaleOnce(): void
    {
        $translator = new class implements TranslatorInterface {
            public function trans(string $key, array $replace = [], ?string $locale = null): string
            {
                return $key;
            }

            public function getLocale(): string
            {
                return 'en';
            }

            public function setLocale(string $locale): void
            {
            }
        };
        $provider = new class implements WorkspacePresentationProviderInterface {
            public int $calls = 0;

            public function present(array $workspaces, string $locale): array
            {
                ++$this->calls;
                foreach ($workspaces as &$workspace) {
                    $workspace['name'] = $locale . ':' . ($workspace['name'] ?? '');
                }

                unset($workspace);

                return $workspaces;
            }
        };
        $registry = new WorkspacePresentationRegistry($translator);
        $registry->register($provider);
        $registry->register($provider);

        $presented = $registry->many([
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ]);

        $this->assertSame(1, $provider->calls);
        $this->assertSame('en:First', $presented[0]['name']);
        $this->assertSame('en:Second', $presented[1]['name']);
    }
}
