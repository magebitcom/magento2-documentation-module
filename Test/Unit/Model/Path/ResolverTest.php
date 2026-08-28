<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Path;

use Magebit\Documentation\Model\Path\Resolver;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var ComponentRegistrarInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private ComponentRegistrarInterface&MockObject $registrar;

    /**
     * @var Resolver
     */
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->root = (string)realpath(sys_get_temp_dir()) . '/magedoc-' . uniqid('', true);

        mkdir($this->root . '/Vendor_A/Docs/advanced', 0777, true);
        mkdir($this->root . '/Vendor_B/Docs', 0777, true);
        mkdir($this->root . '/Vendor_AOther/Docs', 0777, true);
        file_put_contents($this->root . '/Vendor_A/Docs/intro.md', '# Intro');
        file_put_contents($this->root . '/Vendor_A/Docs/advanced/api.md', '# Api');
        file_put_contents($this->root . '/Vendor_A/secret.env', 'KEY=1');
        file_put_contents($this->root . '/Vendor_A/CHANGELOG.md', '# A changes');
        file_put_contents($this->root . '/Vendor_B/Docs/guide.md', '# Guide');
        file_put_contents($this->root . '/Vendor_B/Docs/CHANGELOG.md', '# B changes');
        file_put_contents($this->root . '/Vendor_AOther/Docs/other.md', '# Other');
        mkdir($this->root . '/Vendor_A/Docs/v1.2');
        file_put_contents($this->root . '/Vendor_A/Docs/v1.2/README', 'no extension');

        $this->registrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->registrar->method('getPath')->willReturnCallback(
            fn (string $type, string $name): ?string => $type === ComponentRegistrar::MODULE
                && is_dir($this->root . '/' . $name)
                    ? $this->root . '/' . $name
                    : null
        );

        $this->resolver = new Resolver($this->registrar, new File());
    }

    protected function tearDown(): void
    {
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testResolvesRelativePathAgainstDeclaringModule(): void
    {
        $this->assertSame(
            $this->root . '/Vendor_A/Docs',
            $this->resolver->resolveSectionRoot('Vendor_A', 'Docs')
        );
    }

    public function testResolvesCrossModulePath(): void
    {
        $this->assertSame(
            $this->root . '/Vendor_B/Docs',
            $this->resolver->resolveSectionRoot('Vendor_A', 'Vendor_B::Docs')
        );
    }

    public function testReturnsNullForUnknownModule(): void
    {
        $this->assertNull($this->resolver->resolveSectionRoot('Vendor_A', 'Vendor_Missing::Docs'));
    }

    public function testReturnsNullWhenSectionRootDoesNotExist(): void
    {
        $this->assertNull($this->resolver->resolveSectionRoot('Vendor_A', 'NoSuchFolder'));
    }

    public function testReturnsNullWhenSectionRootIsNotADirectory(): void
    {
        $this->assertNull($this->resolver->resolveSectionRoot('Vendor_A', 'secret.env'));
    }

    public function testRefusesSectionRootOutsideTheModule(): void
    {
        $this->assertNull($this->resolver->resolveSectionRoot('Vendor_A', '../Vendor_B/Docs'));
    }

    public function testRefusesEmptyPathAfterAModulePrefix(): void
    {
        $this->assertNull($this->resolver->resolveSectionRoot('Vendor_A', 'Vendor_B::'));
        $this->assertNull($this->resolver->resolveChangelogFile('Vendor_A', 'Vendor_B::'));
    }

    public function testResolvesFileInsideSectionRoot(): void
    {
        $root = $this->root . '/Vendor_A/Docs';

        $this->assertSame(
            $root . '/advanced/api.md',
            $this->resolver->resolveFile($root, 'advanced/api.md', ['md'])
        );
    }

    public function testRefusesTraversalOutOfSectionRoot(): void
    {
        $root = $this->root . '/Vendor_A/Docs';

        $this->assertNull($this->resolver->resolveFile($root, '../secret.env', ['md']));
        $this->assertNull($this->resolver->resolveFile($root, '../../Vendor_B/Docs/guide.md', ['md']));
    }

    public function testRefusesSiblingDirectorySharingAPrefix(): void
    {
        $root = $this->root . '/Vendor_A';

        $this->assertNull($this->resolver->resolveFile($root, '../Vendor_AOther/Docs/other.md', ['md']));
    }

    public function testRefusesDisallowedExtension(): void
    {
        $root = $this->root . '/Vendor_A';

        $this->assertNull($this->resolver->resolveFile($root, 'secret.env', ['md']));
    }

    public function testExtensionCheckIsCaseInsensitive(): void
    {
        $root = $this->root . '/Vendor_A/Docs';
        file_put_contents($root . '/UPPER.MD', '# Upper');

        $this->assertSame($root . '/UPPER.MD', $this->resolver->resolveFile($root, 'UPPER.MD', ['md']));
        $this->assertSame($root . '/intro.md', $this->resolver->resolveFile($root, 'intro.md', ['MD']));
    }

    public function testRefusesFileWithoutExtensionInsideADottedDirectory(): void
    {
        $root = $this->root . '/Vendor_A/Docs';

        $this->assertNull($this->resolver->resolveFile($root, 'v1.2/README', ['md']));
    }

    public function testRefusesNullByteInPath(): void
    {
        $root = $this->root . '/Vendor_A/Docs';

        $this->assertNull($this->resolver->resolveFile($root, "intro.md\0.png", ['md']));
    }

    public function testResolvesChangelogInsideDeclaringModule(): void
    {
        $this->assertSame(
            ['path' => $this->root . '/Vendor_A/CHANGELOG.md', 'fileName' => 'CHANGELOG.md'],
            $this->resolver->resolveChangelogFile('Vendor_A', 'CHANGELOG.md')
        );
    }

    public function testResolvesChangelogInAnotherModule(): void
    {
        $this->assertSame(
            ['path' => $this->root . '/Vendor_B/Docs/CHANGELOG.md', 'fileName' => 'CHANGELOG.md'],
            $this->resolver->resolveChangelogFile('Vendor_A', 'Vendor_B::Docs/CHANGELOG.md')
        );
    }

    public function testRefusesChangelogOutsideTheModule(): void
    {
        $this->assertNull($this->resolver->resolveChangelogFile('Vendor_A', '../Vendor_B/Docs/CHANGELOG.md'));
    }

    public function testRefusesChangelogWithDisallowedExtension(): void
    {
        $this->assertNull($this->resolver->resolveChangelogFile('Vendor_A', 'secret.env'));
    }

    public function testReturnsNullForChangelogInUnknownModule(): void
    {
        $this->assertNull($this->resolver->resolveChangelogFile('Vendor_A', 'Vendor_Missing::CHANGELOG.md'));
    }
}
