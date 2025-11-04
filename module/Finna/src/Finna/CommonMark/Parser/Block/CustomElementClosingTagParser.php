<?php

/**
 * Custom element closing tag block continue parser
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
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Custom element closing tag block continue parser
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementClosingTagParser extends AbstractBlockContinueParser
{
    /**
     * The current block being parsed by this parser
     *
     * @var CustomElementClosingTag
     */
    protected CustomElementClosingTag $block;

    /**
     * CustomElementClosingTagParser constructor.
     *
     * @param CustomElementClosingTag $block Block
     */
    public function __construct(CustomElementClosingTag $block)
    {
        $this->block = $block;
    }

    /**
     * Return the current block being parsed by this parser
     *
     * @return CustomElementClosingTag
     */
    public function getBlock(): CustomElementClosingTag
    {
        return $this->block;
    }

    /**
     * Return whether we are interested in possibly lazily parsing any subsequent
     * lines
     *
     * @return bool
     */
    public function canHaveLazyContinuationLines(): bool
    {
        return true;
    }

    /**
     * Attempt to parse the given line
     *
     * @param Cursor                       $cursor            Cursor
     * @param BlockContinueParserInterface $activeBlockParser Parser
     *
     * @return BlockContinue|null
     */
    public function tryContinue(
        Cursor $cursor,
        BlockContinueParserInterface $activeBlockParser
    ): ?BlockContinue {
        return BlockContinue::none();
    }

    /**
     * Add the given line of text to the current block
     *
     * @param string $line Line
     *
     * @return void
     */
    public function addLine(string $line): void
    {
        $line = trim($line);
        if ('' !== $line) {
            // Create a new Paragraph node for the remaining contents.
            $paragraph = new Paragraph();
            $paragraph->appendChild(new Text($line));
            $this->getBlock()->appendChild($paragraph);
        }
    }
}
