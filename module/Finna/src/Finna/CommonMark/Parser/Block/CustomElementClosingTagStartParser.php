<?php

/**
 * Custom element closing tag block start parser
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\CommonMark\Parser\Block;

use Finna\CommonMark\Node\Block\CustomElementClosingTag;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

use function strlen;

/**
 * Custom element closing tag block start parser
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementClosingTagStartParser implements BlockStartParserInterface
{
    /**
     * Regex for matching custom element closing tags
     *
     * @var string
     */
    public const CLOSING_REGEX = '/<\/([A-Za-z][A-Za-z0-9]*-[A-Za-z0-9-]+)*\s*>/';

    /**
     * Check whether we should handle the block at the current position
     *
     * @param Cursor                       $cursor      A cloned copy of the cursor
     * at the current parsing location
     * @param MarkdownParserStateInterface $parserState Additional information about
     * the state of the Markdown parser
     *
     * @return BlockStart|null The BlockStart that has been identified, or null if
     * the block doesn't match here
     */
    public function tryStart(
        Cursor $cursor,
        MarkdownParserStateInterface $parserState
    ): ?BlockStart {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '<') {
            return BlockStart::none();
        }

        $tmpCursor = clone $cursor;
        $tmpCursor->advanceToNextNonSpaceOrTab();
        $line = $tmpCursor->getRemainder();

        $matches = [];
        $match = preg_match(self::CLOSING_REGEX, $line, $matches);
        if (1 === $match) {
            $cursor->advanceToNextNonSpaceOrTab();
            $cursor->advanceBy(strlen($matches[0]));

            $name = mb_strtolower($matches[1], 'UTF-8');

            return BlockStart::of(
                new CustomElementClosingTagParser(
                    new CustomElementClosingTag($name)
                )
            )->at($cursor);
        }

        return BlockStart::none();
    }
}
