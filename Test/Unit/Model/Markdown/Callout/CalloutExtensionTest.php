<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Markdown\Callout;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Magebit\Documentation\Model\Markdown\Callout\CalloutExtension;
use PHPUnit\Framework\TestCase;

class CalloutExtensionTest extends TestCase
{
    public function testTurnsAMarkedBlockquoteIntoACalloutWithATitle(): void
    {
        $html = $this->convert("> [!NOTE]\n> Body text");

        $this->assertStringContainsString('<aside class="doc-callout _note">', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">Note</p>', $html);
        $this->assertStringContainsString('<p>Body text</p>', $html);
        $this->assertStringNotContainsString('[!NOTE]', $html);
        $this->assertStringNotContainsString('<blockquote>', $html);
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public function typeProvider(): array
    {
        return [
            'tip' => ['TIP', '_tip', 'Tip'],
            'important' => ['IMPORTANT', '_important', 'Important'],
            'warning' => ['WARNING', '_warning', 'Warning'],
            'caution' => ['CAUTION', '_caution', 'Caution'],
            'lower case marker' => ['warning', '_warning', 'Warning'],
        ];
    }

    /**
     * @dataProvider typeProvider
     * @param string $marker
     * @param string $class
     * @param string $title
     */
    public function testKnowsEveryGithubCalloutType(string $marker, string $class, string $title): void
    {
        $html = $this->convert("> [!$marker]\n> Body");

        $this->assertStringContainsString('<aside class="doc-callout ' . $class . '">', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">' . $title . '</p>', $html);
    }

    public function testKeepsTextWrittenOnTheMarkerLine(): void
    {
        $html = $this->convert("> [!TIP] Same line\n> Next line");

        $this->assertStringContainsString('<p>Same line', $html);
        $this->assertStringContainsString('Next line</p>', $html);
        $this->assertStringNotContainsString('[!TIP]', $html);
    }

    public function testKeepsEverythingElseInsideTheCallout(): void
    {
        $html = $this->convert("> [!WARNING]\n> First\n>\n> - one\n> - two\n>\n> ```\n> code\n> ```");

        $this->assertStringContainsString('<p>First</p>', $html);
        $this->assertStringContainsString('<li>two</li>', $html);
        $this->assertStringContainsString('<code>code', $html);
        $this->assertStringContainsString('</aside>', $html);
    }

    public function testLeavesOrdinaryAndUnknownBlockquotesAlone(): void
    {
        $html = $this->convert("> Plain quote\n\n> [!FOO]\n> Unknown\n\n> Text first [!NOTE]");

        $this->assertSame(3, substr_count($html, '<blockquote>'));
        $this->assertStringContainsString('[!FOO]', $html);
        $this->assertStringNotContainsString('doc-callout', $html);
    }

    /**
     * @param string $markdown
     * @return string
     */
    private function convert(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'strip']);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new CalloutExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
