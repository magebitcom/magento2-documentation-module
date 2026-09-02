<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Callout;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;

/**
 * Swaps every blockquote that starts with a [!TYPE] marker for a Callout node, in the parsed document.
 */
class CalloutProcessor
{
    private const MARKER_PATTERN = '/^\[!(note|tip|important|warning|caution)\](?:[ \t]+|$)/i';

    /**
     * Convert every marked blockquote once the whole document is parsed.
     *
     * @param DocumentParsedEvent $event
     * @return void
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        // Collect first, because replacing nodes while walking would skip their neighbours.
        $quotes = [];
        $walker = $event->getDocument()->walker();

        while ($walkerEvent = $walker->next()) {
            $node = $walkerEvent->getNode();
            if ($walkerEvent->isEntering() && $node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        foreach ($quotes as $quote) {
            $this->convert($quote);
        }
    }

    /**
     * Replace one blockquote with a callout when its first line is a marker.
     *
     * @param BlockQuote $quote
     * @return void
     */
    private function convert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        $text = $paragraph instanceof Paragraph ? $paragraph->firstChild() : null;

        if (!$text instanceof Text || !preg_match(self::MARKER_PATTERN, $text->getLiteral(), $match)) {
            return;
        }

        $this->stripMarker($text, strlen($match[0]));

        $callout = new Callout(strtolower($match[1]));
        foreach ($quote->children() as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }

    /**
     * Remove the marker, and with it whatever the marker line left empty behind.
     *
     * @param Text $text
     * @param int $length
     * @return void
     */
    private function stripMarker(Text $text, int $length): void
    {
        $remaining = substr($text->getLiteral(), $length);
        if ($remaining !== '') {
            $text->setLiteral($remaining);

            return;
        }

        $paragraph = $text->parent();
        $lineBreak = $text->next();

        $text->detach();
        if ($lineBreak instanceof Newline) {
            $lineBreak->detach();
        }

        if ($paragraph !== null && !$paragraph->hasChildren()) {
            $paragraph->detach();
        }
    }
}
