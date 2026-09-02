<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Markdown\Mermaid;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Magebit\Documentation\Model\Markdown\Mermaid\MermaidExtension;
use PHPUnit\Framework\TestCase;

class MermaidExtensionTest extends TestCase
{
    public function testTurnsAMermaidFenceIntoADiagramBlockHoldingTheEscapedSource(): void
    {
        $html = $this->convert("```mermaid\ngraph TD\n  A-->B\n  B-->C[\"<x>\"]\n```");

        $this->assertStringContainsString('<div class="doc-diagram" data-doc-mermaid>', $html);
        $this->assertStringContainsString('<pre class="doc-diagram-source">graph TD', $html);
        $this->assertStringContainsString('C[&quot;&lt;x&gt;&quot;]', $html);
        $this->assertStringNotContainsString('<code', $html);
        $this->assertStringNotContainsString('language-mermaid', $html);
    }

    public function testLeavesEveryOtherFenceToTheDefaultRenderer(): void
    {
        $html = $this->convert("```php\necho 1;\n```\n\n```\nplain\n```");

        $this->assertStringContainsString('<pre><code class="language-php">echo 1;', $html);
        $this->assertStringContainsString('<pre><code>plain', $html);
        $this->assertStringNotContainsString('data-doc-mermaid', $html);
    }

    public function testMatchesTheLanguageWordOnlyAndIgnoresCase(): void
    {
        $html = $this->convert("```Mermaid extra words\ngraph LR\n```\n\n```mermaidjs\nnot a diagram\n```");

        $this->assertSame(1, substr_count($html, 'data-doc-mermaid'));
        $this->assertStringContainsString('<code class="language-mermaidjs">', $html);
    }

    /**
     * @param string $markdown
     * @return string
     */
    private function convert(string $markdown): string
    {
        $environment = new Environment(['html_input' => 'strip']);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MermaidExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
