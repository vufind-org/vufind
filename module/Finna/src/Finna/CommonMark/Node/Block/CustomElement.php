<?php

/**
 * Custom element block node
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

namespace Finna\CommonMark\Node\Block;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;

/**
 * Custom element block node
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElement extends AbstractBlock
{
    /**
     * Custom element name
     *
     * @var string
     */
    protected string $name;

    /**
     * Opening tag
     *
     * @var string
     */
    protected string $openingTag;

    /**
     * Can the custom element be server-side rendered
     *
     * @var bool
     */
    protected bool $canSsr;

    /**
     * Is the block closed
     *
     * @var bool
     */
    protected bool $closed;

    /**
     * CustomElement constructor.
     *
     * @param string $name       Custom element name
     * @param string $openingTag Matched opening tag
     * @param bool   $canSsr     Can the element be server-side rendered
     */
    public function __construct(
        string $name,
        string $openingTag,
        bool $canSsr
    ) {
        parent::__construct();
        $this->name = $name;
        $this->openingTag = $openingTag;
        $this->canSsr = $canSsr;
        $this->closed = false;
    }

    /**
     * Get custom element name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get custom element opening tag
     *
     * @return string
     */
    public function getOpeningTag(): string
    {
        return $this->openingTag;
    }

    /**
     * Should the custom element be server-side rendered
     *
     * @return bool
     */
    public function shouldSsr(): bool
    {
        return $this->canSsr
            && !str_contains($this->openingTag, 'ssr="false"');
    }

    /**
     * Is the block closed
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Append a child node.
     *
     * @param Node $child Node
     *
     * @return void
     */
    public function appendChild(Node $child): void
    {
        parent::appendChild($child);
        if ($child instanceof CustomElementClosingTag) {
            // Any custom element closing tag will close this block.
            $this->closed = true;
        }
    }
}
