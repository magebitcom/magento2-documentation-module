<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Search;

use Magebit\Documentation\Model\Search\Snippet;
use PHPUnit\Framework\TestCase;

class SnippetTest extends TestCase
{
    public function testReturnsAWindowAroundTheMatch(): void
    {
        $body = str_repeat('a ', 100) . 'INVOICE lives here ' . str_repeat('b ', 100);

        $snippet = (new Snippet())->extract($body, 'invoice', 40);

        $this->assertStringContainsString('INVOICE', $snippet);
        $this->assertLessThanOrEqual(46, mb_strlen($snippet));
    }

    public function testPrefixesAndSuffixesWithAnEllipsisOnlyWhenTruncated(): void
    {
        $snippet = (new Snippet())->extract('short body with invoice', 'invoice', 160);

        $this->assertSame('short body with invoice', $snippet);
    }

    public function testFallsBackToTheStartOfTheBodyWhenTheQueryIsAbsent(): void
    {
        $this->assertStringStartsWith('lorem', (new Snippet())->extract('lorem ipsum', 'zzz', 160));
    }

    public function testStartsALongBodyAtItsBeginningWhenTheQueryIsAbsent(): void
    {
        $body = 'lorem ipsum ' . str_repeat('c ', 100);

        $snippet = (new Snippet())->extract($body, 'zzz', 40);

        $this->assertSame('lorem ipsum ' . str_repeat('c ', 14) . '...', $snippet);
    }

    public function testDropsTheLeadingEllipsisWhenTheMatchIsAtTheStart(): void
    {
        $body = 'invoice first ' . str_repeat('d ', 100);

        $snippet = (new Snippet())->extract($body, 'invoice', 40);

        $this->assertStringStartsWith('invoice first', $snippet);
        $this->assertStringEndsWith('...', $snippet);
    }

    public function testDropsTheTrailingEllipsisWhenTheMatchIsAtTheEnd(): void
    {
        $body = str_repeat('e ', 100) . 'last invoice';

        $snippet = (new Snippet())->extract($body, 'invoice', 40);

        $this->assertStringStartsWith('...', $snippet);
        $this->assertStringEndsWith('last invoice', $snippet);
        $this->assertSame(43, mb_strlen($snippet));
    }

    public function testCollapsesLineBreaksSoTheSnippetIsOneLine(): void
    {
        $snippet = (new Snippet())->extract("first line\n\n  second   line", 'second', 160);

        $this->assertSame('first line second line', $snippet);
    }

    public function testIgnoresTheCaseOfTheQuery(): void
    {
        $body = str_repeat('f ', 100) . 'the Invoice Screen ' . str_repeat('g ', 100);

        $this->assertStringContainsString('Invoice Screen', (new Snippet())->extract($body, 'INVOICE', 40));
    }

    public function testReturnsNothingForALengthBelowOne(): void
    {
        $this->assertSame('', (new Snippet())->extract('some body with invoice', 'invoice', 0));
    }

    public function testReturnsTheStartOfTheBodyForAnEmptyQuery(): void
    {
        $body = 'hello ' . str_repeat('h ', 100);

        $this->assertStringStartsWith('hello', (new Snippet())->extract($body, '', 40));
    }
}
