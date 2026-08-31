<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Highlighter;

use Magebit\Documentation\Model\Highlighter\ClientSide;
use PHPUnit\Framework\TestCase;

class ClientSideTest extends TestCase
{
    /**
     * @var ClientSide
     */
    private ClientSide $highlighter;

    protected function setUp(): void
    {
        $this->highlighter = new ClientSide();
    }

    public function testAddsTheHookAttributeNamingTheLanguage(): void
    {
        $this->assertSame(
            '<pre><code class="language-php" data-doc-highlight="php">echo 1;</code></pre>',
            $this->highlighter->decorate('<pre><code class="language-php">echo 1;</code></pre>')
        );
    }

    public function testDecoratesEveryFencedBlockOnThePage(): void
    {
        $html = '<pre><code class="language-php">echo 1;</code></pre>'
            . '<p>text</p>'
            . '<pre><code class="language-nginx">server {}</code></pre>';

        $this->assertSame(
            '<pre><code class="language-php" data-doc-highlight="php">echo 1;</code></pre>'
            . '<p>text</p>'
            . '<pre><code class="language-nginx" data-doc-highlight="nginx">server {}</code></pre>',
            $this->highlighter->decorate($html)
        );
    }

    public function testKeepsTheAttributesThePageAlreadyHas(): void
    {
        $this->assertSame(
            '<pre class="wide"><code class="hljs language-js" id="one" data-doc-highlight="js">let a;</code></pre>',
            $this->highlighter->decorate(
                '<pre class="wide"><code class="hljs language-js" id="one">let a;</code></pre>'
            )
        );
    }

    public function testLeavesCodeBlocksWithoutALanguageAlone(): void
    {
        $html = '<pre><code>plain text</code></pre>';

        $this->assertSame($html, $this->highlighter->decorate($html));
    }

    public function testLeavesInlineCodeAlone(): void
    {
        $html = '<p>Run <code>bin/magento</code> first.</p>';

        $this->assertSame($html, $this->highlighter->decorate($html));
    }

    public function testLeavesInlineCodeAloneEvenWhenItCarriesALanguageClass(): void
    {
        $html = '<p>Run <code class="language-bash">bin/magento</code> first.</p>';

        $this->assertSame($html, $this->highlighter->decorate($html));
    }

    public function testDecoratingTwiceChangesNothing(): void
    {
        $once = $this->highlighter->decorate('<pre><code class="language-php">echo 1;</code></pre>');

        $this->assertSame($once, $this->highlighter->decorate($once));
    }
}
