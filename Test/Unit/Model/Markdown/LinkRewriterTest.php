<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Markdown;

use Magebit\Documentation\Model\Markdown\EnvironmentFactory;
use Magebit\Documentation\Model\Markdown\LinkRewriter;
use Magebit\Documentation\Model\Markdown\Renderer;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LinkRewriterTest extends TestCase
{
    /**
     * @var Renderer
     */
    private Renderer $renderer;

    protected function setUp(): void
    {
        $urlBuilder = $this->createMock(UrlBuilder::class);
        $urlBuilder->method('page')->willReturnCallback(
            static fn (string $m, string $s, string $p): string => "/admin/doc/{$m}/{$s}/{$p}"
        );
        $urlBuilder->method('asset')->willReturnCallback(
            static fn (string $m, string $s, string $p): string => "/admin/asset/{$m}/{$s}/{$p}"
        );

        $this->renderer = new Renderer(
            new EnvironmentFactory(['gfm' => new GithubFlavoredMarkdownExtension()], []),
            new LinkRewriter($urlBuilder),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testDoesNotDoubleEscapeQueryStrings(): void
    {
        $html = $this->render('[x](https://example.com/?a=1&b=2)');

        $this->assertStringContainsString('href="https://example.com/?a=1&amp;b=2"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testRewritesRelativeMarkdownLinks(): void
    {
        $html = $this->render('[x](other.md)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/advanced/other.md"', $html);
    }

    public function testResolvesParentDirectoryInMarkdownLinks(): void
    {
        $html = $this->render('[x](../intro.md)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/intro.md"', $html);
    }

    public function testRewritesRelativeImagesToTheAssetController(): void
    {
        $html = $this->render('![alt](diagram.png)');

        $this->assertStringContainsString('src="/admin/asset/Vendor_A/Guide/advanced/diagram.png"', $html);
    }

    public function testLeavesExtensionlessRelativeLinksAlone(): void
    {
        $html = $this->render('[x](configuration)');

        $this->assertStringContainsString('href="configuration"', $html);
        $this->assertStringNotContainsString('configuration.md', $html);
    }

    public function testLeavesAnchorsAbsolutePathsAndSchemesAlone(): void
    {
        $html = $this->render("[a](#top)\n\n[b](/admin/dashboard)\n\n[c](mailto:info@magebit.com)");

        $this->assertStringContainsString('href="#top"', $html);
        $this->assertStringContainsString('href="/admin/dashboard"', $html);
        $this->assertStringContainsString('href="mailto:info@magebit.com"', $html);
    }

    public function testStripsRawHtml(): void
    {
        $this->assertStringNotContainsString('<script>', $this->render('<script>alert(1)</script>'));
    }

    public function testEscapesGeneratedPageUrlQueryStringsExactlyOnce(): void
    {
        $urlBuilder = $this->createMock(UrlBuilder::class);
        $urlBuilder->method('page')->willReturn('/admin/doc?module=Vendor_A&section=Guide&path=other.md');
        $renderer = new Renderer(
            new EnvironmentFactory(['gfm' => new GithubFlavoredMarkdownExtension()], []),
            new LinkRewriter($urlBuilder),
            $this->createMock(LoggerInterface::class)
        );

        $html = $renderer->render('[x](other.md)', [
            'module' => 'Vendor_A',
            'section' => 'Guide',
            'path' => 'advanced/page.md',
        ]);

        $this->assertStringContainsString(
            'href="/admin/doc?module=Vendor_A&amp;section=Guide&amp;path=other.md"',
            $html
        );
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testClampsParentSegmentsAtTheSectionRoot(): void
    {
        $html = $this->render('[x](../../../../etc/passwd.md)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/etc/passwd.md"', $html);
        $this->assertStringNotContainsString('..', $html);
    }

    public function testClampsParentSegmentsInImagePaths(): void
    {
        $html = $this->render('![alt](../../../secret.png)');

        $this->assertStringContainsString('src="/admin/asset/Vendor_A/Guide/secret.png"', $html);
        $this->assertStringNotContainsString('..', $html);
    }

    public function testDropsCurrentDirectorySegments(): void
    {
        $html = $this->render('[x](./sub/./other.md)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/advanced/sub/other.md"', $html);
    }

    public function testLeavesProtocolRelativeAndUppercaseSchemeUrlsAlone(): void
    {
        $html = $this->render("[a](//evil.example/page.md)\n\n[b](HTTPS://example.com/page.md)");

        $this->assertStringContainsString('href="//evil.example/page.md"', $html);
        $this->assertStringContainsString('href="HTTPS://example.com/page.md"', $html);
    }

    public function testRewritesUppercaseMarkdownExtensions(): void
    {
        $html = $this->render('[x](Other.MD)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/advanced/Other.MD"', $html);
    }

    public function testLeavesEmptyUrlsAlone(): void
    {
        $html = $this->render("[x]()\n\n![alt]()");

        $this->assertStringContainsString('href=""', $html);
        $this->assertStringContainsString('src=""', $html);
    }

    public function testLeavesAbsoluteAndExternalUrlsAloneEvenWhenTheyWouldBeRewritten(): void
    {
        $html = $this->render("[a](/docs/other.md)\n\n![b](/media/logo.png)\n\n![c](https://cdn.example/logo.png)");

        $this->assertStringContainsString('href="/docs/other.md"', $html);
        $this->assertStringContainsString('src="/media/logo.png"', $html);
        $this->assertStringContainsString('src="https://cdn.example/logo.png"', $html);
    }

    public function testKeepsTheFragmentOnRewrittenMarkdownLinks(): void
    {
        $html = $this->render('[x](../intro.md#installation)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/intro.md#installation"', $html);
    }

    public function testKeepsQueryStringsOutOfTheResolvedImagePath(): void
    {
        $html = $this->render('![alt](diagram.png?v=2)');

        $this->assertStringContainsString('src="/admin/asset/Vendor_A/Guide/advanced/diagram.png"', $html);
        $this->assertStringNotContainsString('v=2', $html);
    }

    public function testKeepsTheFragmentOnRewrittenImages(): void
    {
        $html = $this->render('![alt](icons.svg#gear)');

        $this->assertStringContainsString('src="/admin/asset/Vendor_A/Guide/advanced/icons.svg#gear"', $html);
    }

    public function testAQueryStringContainingASlashDoesNotBecomePathSegments(): void
    {
        $html = $this->render('[x](other.md?a=b/c)');

        $this->assertStringContainsString('href="/admin/doc/Vendor_A/Guide/advanced/other.md"', $html);
        $this->assertStringNotContainsString('b/c', $html);
    }

    public function testDecodesPercentEncodedFileNames(): void
    {
        $renderer = $this->rendererExpectingPagePath('advanced/my file.md');

        $renderer->render('[x](my%20file.md)', $this->context());
    }

    public function testEncodedParentSegmentsCannotClimbOutOfTheSection(): void
    {
        $renderer = $this->rendererExpectingPagePath('secret.md');

        $renderer->render('[x](%2e%2e%2f%2e%2e%2fsecret.md)', $this->context());
    }

    public function testLeavesAnchorAndSchemeUrlsAloneOnImagesToo(): void
    {
        $html = $this->render("![a](#top)\n\n![b](tel:+37100000000)");

        $this->assertStringContainsString('src="#top"', $html);
        $this->assertStringContainsString('src="tel:+37100000000"', $html);
    }

    /**
     * A renderer whose UrlBuilder asserts the exact path the rewriter resolved.
     *
     * @param string $expectedPath
     * @return Renderer
     */
    private function rendererExpectingPagePath(string $expectedPath): Renderer
    {
        $urlBuilder = $this->createMock(UrlBuilder::class);
        $urlBuilder->expects($this->once())
            ->method('page')
            ->with('Vendor_A', 'Guide', $expectedPath)
            ->willReturn('/resolved');

        return new Renderer(
            new EnvironmentFactory(['gfm' => new GithubFlavoredMarkdownExtension()], []),
            new LinkRewriter($urlBuilder),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @return array{module:string,section:string,path:string}
     */
    private function context(): array
    {
        return ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'advanced/page.md'];
    }

    /**
     * @param string $markdown
     * @return string
     */
    private function render(string $markdown): string
    {
        return $this->renderer->render($markdown, $this->context());
    }
}
