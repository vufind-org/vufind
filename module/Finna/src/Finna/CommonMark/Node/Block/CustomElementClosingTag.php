<?php

/**
 * Custom element closing tag block node
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

/**
 * Custom element closing tag block node
 *
 * @category VuFind
 * @package  CommonMark
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomElementClosingTag extends AbstractBlock
{
    /**
     * Custom element name
     *
     * @var string
     */
    protected string $name;

    /**
     * CustomElementClosingTag constructor.
     *
     * @param string $name Custom element name
     */
    public function __construct(string $name)
    {
        parent::__construct();
        $this->name = $name;
    }

    /**
     * Get custom element closing tag
     *
     * @return string
     */
    public function getClosingTag(): string
    {
        return '</' . $this->name . '>';
    }
}
