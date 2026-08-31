<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Console\Command;

use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Console\Command\ValidateDocumentation;
use Magebit\Documentation\Model\Config\Converter;
use Magebit\Documentation\Model\Config\Data as ConfigData;
use Magento\Framework\Acl\AclResource\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @phpstan-import-type DocModule from Converter
 * @phpstan-import-type DocSection from Converter
 */
class ValidateDocumentationTest extends TestCase
{
    private const ACL_RESOURCE = 'Vendor_A::docs';

    public function testFailsWhenASectionPathDoesNotResolve(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([$this->sectionConfig('Guide', 'Vendor_B::Docs')])],
            $resolver
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Vendor_A / Guide', $tester->getDisplay());
        $this->assertStringContainsString('Vendor_B::Docs', $tester->getDisplay());
    }

    public function testFailsWhenAChangelogFileDoesNotResolve(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn('/abs/path');
        $resolver->method('resolveChangelogFile')->willReturn(null);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([$this->changelogConfig('Changelog', 'CHANGELOG.md')])],
            $resolver
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Vendor_A / Changelog', $tester->getDisplay());
        $this->assertStringContainsString('CHANGELOG.md', $tester->getDisplay());
    }

    public function testResolvesAChangelogAsAFileAndADocumentationSectionAsADirectory(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveSectionRoot')
            ->with('Vendor_A', 'Docs')
            ->willReturn('/abs/path');
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'CHANGELOG.md')
            ->willReturn(['path' => '/abs/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Docs'),
                $this->changelogConfig('Changelog', 'CHANGELOG.md'),
            ])],
            $resolver
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testFailsWhenAnAclResourceIsNotDeclared(): void
    {
        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Docs', 'Vendor_A::typo'),
            ])],
            aclResources: [['id' => self::ACL_RESOURCE, 'children' => []]]
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Vendor_A / Guide', $tester->getDisplay());
        $this->assertStringContainsString('Vendor_A::typo', $tester->getDisplay());
    }

    public function testAcceptsAnAclResourceDeclaredBelowTheTreeRoot(): void
    {
        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Docs', self::ACL_RESOURCE),
            ])],
            aclResources: [[
                'id' => 'Magento_Backend::admin',
                'children' => [
                    ['id' => 'Magento_Backend::system', 'children' => [
                        ['id' => self::ACL_RESOURCE, 'children' => []],
                    ]],
                ],
            ]]
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testReportsEveryFailureRatherThanStoppingAtTheFirst(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);

        $tester = $this->runCommand(
            [
                'Vendor_A' => $this->moduleConfig([$this->sectionConfig('Guide', 'Docs')]),
                'Vendor_B' => $this->moduleConfig([$this->sectionConfig('Manual', 'Docs')]),
            ],
            $resolver
        );

        $this->assertStringContainsString('Vendor_A / Guide', $tester->getDisplay());
        $this->assertStringContainsString('Vendor_B / Manual', $tester->getDisplay());
    }

    public function testSucceedsWhenEveryPathResolvesAndEveryAclResourceExists(): void
    {
        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Docs', self::ACL_RESOURCE),
            ])],
            aclResources: [['id' => self::ACL_RESOURCE, 'children' => []]]
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('Vendor_A / Guide', $tester->getDisplay());
    }

    public function testSucceedsWhenNoDocumentationIsRegistered(): void
    {
        $this->assertSame(Command::SUCCESS, $this->runCommand([])->getStatusCode());
    }

    public function testWritesFailuresToTheErrorOutput(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([$this->sectionConfig('Guide', 'Docs')])],
            $resolver,
            separateStderr: true
        );

        // A CI step that redirects stdout must still be told why the build broke.
        $this->assertStringContainsString('Vendor_A / Guide', $tester->getErrorOutput());
        $this->assertStringNotContainsString('Vendor_A / Guide', $tester->getDisplay());
    }

    public function testReportsBothTheBadPathAndTheBadAclOfOneSection(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Vendor_B::Docs', 'Vendor_A::typo'),
            ])],
            $resolver
        );

        $display = $tester->getDisplay();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Vendor_B::Docs', $display);
        $this->assertStringContainsString('Vendor_A::typo', $display);
    }

    public function testSaysWhatItCheckedWhenNothingIsWrong(): void
    {
        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([$this->sectionConfig('Guide', 'Docs')])]
        );

        $this->assertStringContainsString('OK', $tester->getDisplay());
    }

    public function testSurvivesAResourceNodeWhoseChildrenAreNotAnArray(): void
    {
        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleConfig([
                $this->sectionConfig('Guide', 'Docs', self::ACL_RESOURCE),
            ])],
            aclResources: [
                ['id' => 'Magento_Backend::admin', 'children' => 'not an array'],
                ['id' => self::ACL_RESOURCE, 'children' => []],
            ]
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * Run the command over one configuration and return the tester holding its output.
     *
     * @param array<string, DocModule> $config
     * @param PathResolverInterface|null $resolver
     * @param array<int, mixed> $aclResources
     * @param bool $separateStderr
     * @return CommandTester
     */
    private function runCommand(
        array $config,
        ?PathResolverInterface $resolver = null,
        array $aclResources = [],
        bool $separateStderr = false
    ): CommandTester {
        $configData = $this->createMock(ConfigData::class);
        $configData->method('get')->willReturn($config);

        if ($resolver === null) {
            $resolver = $this->createMock(PathResolverInterface::class);
            $resolver->method('resolveSectionRoot')->willReturn('/abs/path');
            $resolver->method('resolveChangelogFile')
                ->willReturn(['path' => '/abs/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);
        }

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAclResources')->willReturn($aclResources);

        $tester = new CommandTester(new ValidateDocumentation($configData, $resolver, $provider));
        $tester->execute([], $separateStderr ? ['capture_stderr_separately' => true] : []);

        return $tester;
    }

    /**
     * @param list<DocSection> $sections
     * @return DocModule
     */
    private function moduleConfig(array $sections): array
    {
        return ['title' => 'A', 'sortOrder' => 10, 'icon' => null, 'sections' => $sections];
    }

    /**
     * @param string $name
     * @param string $path
     * @param string|null $acl
     * @return DocSection
     */
    private function sectionConfig(string $name, string $path, ?string $acl = null): array
    {
        return ['name' => $name, 'path' => $path, 'acl' => $acl, 'sortOrder' => 100, 'isChangelog' => false];
    }

    /**
     * @param string $name
     * @param string $path
     * @return DocSection
     */
    private function changelogConfig(string $name, string $path): array
    {
        return ['name' => $name, 'path' => $path, 'acl' => null, 'sortOrder' => 1000, 'isChangelog' => true];
    }
}
