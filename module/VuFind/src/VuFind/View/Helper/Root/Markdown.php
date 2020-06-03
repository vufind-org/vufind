<?php

/**
 * Class Markdown
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  VuFind\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use League\CommonMark\MarkdownConverterInterface;

/**
 * Helper for transforming markdown to html
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Markdown extends AbstractHelper
{
    /**
     * Markdown converter
     *
     * @var MarkdownConverterInterface
     */
    protected $converter;

    /**
     * Markdown constructor.
     *
     * @param MarkdownConverterInterface $converter Markdown converter
     */
    public function __construct(MarkdownConverterInterface $converter)
    {
        $this->converter = $converter;
    }

    /**
     * Converts markdown to html
     *
     * @param string $markdown Markdown formatted text
     *
     * @return string
     */
    public function __invoke(string $markdown)
    {
        return $this->converter->convertToHtml($markdown);
    }
}
