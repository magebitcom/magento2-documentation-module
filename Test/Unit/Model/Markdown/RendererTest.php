<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Markdown;

use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use Magebit\Documentation\Model\Markdown\EnvironmentFactory;
use Magebit\Documentation\Model\Markdown\LinkRewriter;
use Magebit\Documentation\Model\Markdown\Renderer;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RendererTest extends TestCase
{
    private const PAGE_URL = 'https://shop.test/admin/doc?path=other.md';

    /**
     * @var UrlBuilder&MockObject
     */
    private UrlBuilder $urlBuilder;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlBuilder::class);
        $this->urlBuilder->method('page')->willReturn(self::PAGE_URL);
        $this->urlBuilder->method('asset')->willReturn('https://shop.test/admin/asset?path=diagram.png');
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testKeepsRewrittenPageLinksInternalWhileMarkingRealExternalLinks(): void
    {
        $renderer = $this->renderer([
            'gfm' => new GithubFlavoredMarkdownExtension(),
            'external_link' => new ExternalLinkExtension(),
        ]);

        $html = $renderer->render("[i](other.md)\n\n[e](https://example.com/)", $this->context('advanced/page.md'));

        $this->assertStringContainsString('<a href="' . self::PAGE_URL . '">i</a>', $html);
        $this->assertSame(1, substr_count($html, 'target="_blank"'));
        $this->assertStringContainsString('href="https://example.com/"', $html);
    }

    public function testRegistersTheInjectedExtensionsSoFrontMatterNeverReachesTheOutput(): void
    {
        $renderer = $this->renderer(['front_matter' => new FrontMatterExtension()]);

        $html = $renderer->render("---\ntitle: Hidden\n---\n\nBody text", $this->context('page.md'));

        $this->assertStringNotContainsString('title: Hidden', $html);
        $this->assertStringContainsString('Body text', $html);
    }

    public function testStripsRawHtmlWithoutRelyingOnAnyExtension(): void
    {
        $html = $this->renderer([])->render('<div>raw</div>', $this->context('page.md'));

        $this->assertStringNotContainsString('<div>', $html);
    }

    public function testDropsUnsafeLinkSchemes(): void
    {
        $html = $this->renderer([])->render('[x](javascript:alert(1))', $this->context('page.md'));

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testLetsTheInjectedConfigOverrideTheDefaults(): void
    {
        $renderer = new Renderer(
            new EnvironmentFactory([], ['html_input' => 'allow']),
            new LinkRewriter($this->urlBuilder),
            $this->logger
        );

        $this->assertStringContainsString('<div>', $renderer->render('<div>raw</div>', $this->context('page.md')));
    }

    public function testAcceptsTheNumericStringThatDiXmlProducesForTheNestingLimit(): void
    {
        $renderer = new Renderer(
            new EnvironmentFactory([], ['max_nesting_level' => '50']),
            new LinkRewriter($this->urlBuilder),
            $this->logger
        );

        $this->assertStringContainsString('<p>text</p>', $renderer->render('text', $this->context('page.md')));
    }

    public function testResolvesEachCallAgainstItsOwnPageDirectory(): void
    {
        $this->urlBuilder = $this->createMock(UrlBuilder::class);
        $this->urlBuilder->method('page')->willReturnCallback(
            static fn (string $m, string $s, string $p): string => "/doc/{$p}"
        );
        $renderer = $this->renderer([]);

        $first = $renderer->render('[x](other.md)', $this->context('advanced/page.md'));
        $second = $renderer->render('[x](other.md)', $this->context('intro.md'));

        $this->assertStringContainsString('href="/doc/advanced/other.md"', $first);
        $this->assertStringContainsString('href="/doc/other.md"', $second);
    }

    public function testReturnsNothingAndLogsWhenTheMarkdownCannotBeParsed(): void
    {
        $this->logger->expects($this->once())->method('error');
        $renderer = $this->renderer([]);

        $this->assertSame('', $renderer->render("\xC3\x28", $this->context('page.md')));
    }

    /**
     * @param array<string,ExtensionInterface> $extensions
     * @return Renderer
     */
    private function renderer(array $extensions): Renderer
    {
        return new Renderer(
            new EnvironmentFactory($extensions, []),
            new LinkRewriter($this->urlBuilder),
            $this->logger
        );
    }

    /**
     * @param string $path
     * @return array{module:string,section:string,path:string}
     */
    private function context(string $path): array
    {
        return ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => $path];
    }
}
