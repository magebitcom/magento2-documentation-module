<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Controller\Adminhtml\Asset;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Controller\Adminhtml\Asset\Index;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\Section;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexTest extends TestCase
{
    private const SECTION_ROOT = '/srv/app/code/Vendor/A/Docs';

    private const BYTES = '<svg onload="alert(1)"></svg>';

    private const DEFAULT_PARAMS = ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'diagram.png'];

    public function testReturns404WhenTheSectionIsNotVisibleToTheAdmin(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getSection')->willReturn(null);

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');

        $rendered = $this->render($this->controller(tree: $tree, resolver: $resolver)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
        $this->assertSame('', $rendered['body']);
    }

    public function testReturns404WhenTheSectionPathDoesNotResolve(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);
        $resolver->expects($this->never())->method('resolveFile');

        $rendered = $this->render($this->controller(resolver: $resolver)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    public function testReturns404WhenTheResolverRefusesTheFile(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(self::SECTION_ROOT);
        $resolver->method('resolveFile')->willReturn(null);

        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->expects($this->never())->method('fileGetContents');

        $rendered = $this->render($this->controller(resolver: $resolver, fileDriver: $fileDriver)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    /**
     * @param string $path
     * @return void
     * @dataProvider unusablePathProvider
     */
    public function testReturns404ForAPathThatNamesNoFile(string $path): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->expects($this->never())->method('getSection');

        $rendered = $this->render(
            $this->controller(tree: $tree, params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => $path])
                ->execute()
        );

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    /**
     * @return array<string, array{string}>
     */
    public function unusablePathProvider(): array
    {
        return [
            'empty path' => [''],
            'directory only' => ['images/'],
        ];
    }

    /**
     * @param array<string, string> $params
     * @return void
     * @dataProvider missingCoordinateProvider
     */
    public function testReturns404WhenAModuleOrSectionIsMissing(array $params): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->expects($this->never())->method('getSection');

        $rendered = $this->render($this->controller(tree: $tree, params: $params)->execute());

        $this->assertSame(404, $rendered['code']);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public function missingCoordinateProvider(): array
    {
        return [
            'no module' => [['module' => '', 'section' => 'Guide', 'path' => 'diagram.png']],
            'no section' => [['module' => 'Vendor_A', 'section' => '', 'path' => 'diagram.png']],
        ];
    }

    public function testReturns404WhenTheFileCannotBeRead(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('stat')->willReturn(['size' => 12]);
        $fileDriver->method('fileGetContents')->willThrowException(new FileSystemException(__('nope')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $rendered = $this->render($this->controller(fileDriver: $fileDriver, logger: $logger)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    public function testPassesTheRequestPathToTheResolverWithoutDecodingItAgain(): void
    {
        $seen = null;

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(self::SECTION_ROOT);
        $resolver->method('resolveFile')->willReturnCallback(
            function (string $root, string $path) use (&$seen): ?string {
                $seen = $path;

                return null;
            }
        );

        $this->controller(
            resolver: $resolver,
            params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => '%2e%2e%2fsecret.png']
        )->execute();

        $this->assertSame('%2e%2e%2fsecret.png', $seen);
    }

    public function testResolvesTheFileUnderTheRootTheResolverReturned(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveSectionRoot')
            ->with('Vendor_A', 'Vendor_A::Docs')
            ->willReturn(self::SECTION_ROOT);
        $resolver->expects($this->once())
            ->method('resolveFile')
            ->with(self::SECTION_ROOT, 'images/diagram.png', ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'])
            ->willReturn(self::SECTION_ROOT . '/images/diagram.png');

        $rendered = $this->render(
            $this->controller(
                resolver: $resolver,
                params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'images/diagram.png']
            )->execute()
        );

        $this->assertSame('image/png', $rendered['headers']['Content-Type'] ?? null);
    }

    /**
     * @param string $path
     * @param string $expected
     * @return void
     * @dataProvider mimeTypeProvider
     */
    public function testSendsTheMappedMimeTypeNotOneDerivedFromTheFile(string $path, string $expected): void
    {
        $rendered = $this->render(
            $this->controller(params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => $path])->execute()
        );

        $this->assertNull($rendered['code']);
        $this->assertSame($expected, $rendered['headers']['Content-Type'] ?? null);
        $this->assertSame('inline', $rendered['headers']['Content-Disposition'] ?? null);
        $this->assertSame('nosniff', $rendered['headers']['X-Content-Type-Options'] ?? null);
        $this->assertSame(self::BYTES, $rendered['body']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function mimeTypeProvider(): array
    {
        return [
            'png' => ['diagram.png', 'image/png'],
            'jpg' => ['diagram.jpg', 'image/jpeg'],
            'jpeg' => ['diagram.jpeg', 'image/jpeg'],
            'gif' => ['diagram.gif', 'image/gif'],
            'webp' => ['diagram.webp', 'image/webp'],
            'svg' => ['diagram.svg', 'image/svg+xml'],
            'upper case extension' => ['diagram.PNG', 'image/png'],
        ];
    }

    public function testLocksDownSvgResponsesWithAContentSecurityPolicy(): void
    {
        $rendered = $this->render(
            $this->controller(params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'diagram.svg'])
                ->execute()
        );

        $this->assertSame(
            "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            $rendered['headers']['Content-Security-Policy'] ?? null
        );
    }

    public function testTheSvgPolicySandboxesTheDocumentSoItSurvivesAMergedAdminPolicy(): void
    {
        $rendered = $this->render(
            $this->controller(params: ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'diagram.svg'])
                ->execute()
        );

        $this->assertStringContainsString(
            'sandbox',
            (string)($rendered['headers']['Content-Security-Policy'] ?? '')
        );
    }

    public function testReturns404WhenTheResolvedFileHasNoMappedType(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(self::SECTION_ROOT);
        $resolver->method('resolveFile')->willReturn(self::SECTION_ROOT . '/diagram.bmp');

        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('stat')->willReturn(['size' => 12]);
        $fileDriver->expects($this->never())->method('fileGetContents');

        $rendered = $this->render($this->controller(resolver: $resolver, fileDriver: $fileDriver)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    public function testReturns404ForAFileLargerThanTheCapWithoutReadingIt(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('stat')->willReturn(['size' => 8388609]);
        $fileDriver->expects($this->never())->method('fileGetContents');

        $rendered = $this->render($this->controller(fileDriver: $fileDriver)->execute());

        $this->assertSame(404, $rendered['code']);
        $this->assertSame([], $rendered['headers']);
    }

    public function testServesAFileThatIsExactlyAtTheCap(): void
    {
        $rendered = $this->render($this->controller(fileDriver: $this->fileDriver(8388608))->execute());

        $this->assertNull($rendered['code']);
        $this->assertSame('image/png', $rendered['headers']['Content-Type'] ?? null);
    }

    public function testReturns404WhenTheFileSizeCannotBeTold(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('stat')->willReturn([]);
        $fileDriver->expects($this->never())->method('fileGetContents');

        $rendered = $this->render($this->controller(fileDriver: $fileDriver)->execute());

        $this->assertSame(404, $rendered['code']);
    }

    public function testDoesNotSendAContentSecurityPolicyForAnImageThatCannotCarryScript(): void
    {
        $rendered = $this->render($this->controller()->execute());

        $this->assertArrayNotHasKey('Content-Security-Policy', $rendered['headers']);
    }

    /**
     * Render a result into a mocked response, because a result object has no header getters.
     *
     * @param Raw $result
     * @return array{code: int|null, headers: array<string, string>, body: string|null}
     */
    private function render(Raw $result): array
    {
        $code = null;
        $headers = [];
        $body = null;

        $response = $this->createMock(HttpResponse::class);
        $response->method('setHttpResponseCode')->willReturnCallback(
            function ($value) use (&$code): void {
                $code = (int)$value;
            }
        );
        $response->method('setHeader')->willReturnCallback(
            function ($name, $value) use (&$headers): void {
                $headers[(string)$name] = (string)$value;
            }
        );
        $response->method('setBody')->willReturnCallback(
            function ($value) use (&$body): void {
                $body = (string)$value;
            }
        );

        $result->renderResult($response);

        return ['code' => $code, 'headers' => $headers, 'body' => $body];
    }

    /**
     * @param DocumentationTreeInterface|null $tree
     * @param PathResolverInterface|null $resolver
     * @param FileDriver|null $fileDriver
     * @param LoggerInterface|null $logger
     * @param array<string, string>|null $params
     * @return Index
     */
    private function controller(
        ?DocumentationTreeInterface $tree = null,
        ?PathResolverInterface $resolver = null,
        ?FileDriver $fileDriver = null,
        ?LoggerInterface $logger = null,
        ?array $params = null
    ): Index {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            function ($key, $default = null) use ($params) {
                return ($params ?? self::DEFAULT_PARAMS)[$key] ?? $default;
            }
        );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->method('create')->willReturnCallback(fn (): Raw => new Raw());

        return new Index(
            $context,
            $tree ?? $this->tree(),
            $resolver ?? $this->resolver(),
            $rawFactory,
            $fileDriver ?? $this->fileDriver(),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * A tree that shows one section of one module to the current admin.
     *
     * @return DocumentationTreeInterface
     */
    private function tree(): DocumentationTreeInterface
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getSection')->willReturnCallback(
            fn (string $module, string $section): ?Section => $module === 'Vendor_A' && $section === 'Guide'
                ? new Section('Guide', 'Vendor_A::Docs', null, 10, new Category('', 0, [], []), false)
                : null
        );

        return $tree;
    }

    /**
     * A resolver that accepts every path inside the section root.
     *
     * @return PathResolverInterface
     */
    private function resolver(): PathResolverInterface
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(self::SECTION_ROOT);
        $resolver->method('resolveFile')->willReturnCallback(
            fn (string $root, string $path): string => $root . '/' . $path
        );

        return $resolver;
    }

    /**
     * @param int|null $size Size the file reports, the real length of the bytes by default
     * @return FileDriver
     */
    private function fileDriver(?int $size = null): FileDriver
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('stat')->willReturn(['size' => $size ?? strlen(self::BYTES)]);
        $fileDriver->method('fileGetContents')->willReturn(self::BYTES);

        return $fileDriver;
    }
}
