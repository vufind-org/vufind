<?php

/**
 * Custom element block continue parser
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

use Finna\CommonMark\Node\Block\CustomElement;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Custom element block continue parser
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementParser extends AbstractBlockContinueParser
{
    /**
     * The current block being parsed by this parser
     *
     * @var CustomElement
     */
    protected CustomElement $block;

    /**
     * CustomElementBlockParser constructor.
     *
     * @param CustomElement $block Block
     */
    public function __construct(CustomElement $block)
    {
        $this->block = $block;
    }

    /**
     * Return the current block being parsed by this parser
     *
     * @return CustomElement
     */
    public function getBlock(): CustomElement
    {
        return $this->block;
    }

    /**
     * Return whether we are parsing a container block
     *
     * @return bool
     */
    public function isContainer(): bool
    {
        return true;
    }

    /**
     * Determine whether the current block being parsed can contain the given child
     * block
     *
     * @param AbstractBlock $block Block
     *
     * @return bool
     */
    public function canContain(AbstractBlock $block): bool
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
        if ($this->getBlock()->isClosed()) {
            return BlockContinue::none();
        }
        return BlockContinue::at($cursor);
    }
}
