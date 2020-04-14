<?php
/**
 * Markdown view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Markdown view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Markdown extends \VuFind\View\Helper\Root\Markdown
{
    /**
     * Parsedown parser
     *
     * @var \Parsedown
     */
    protected $parsedown = null;

    /**
     * Return HTML.
     *
     * @param string $markdown Markdown
     *
     * @return string
     */
    public function toHtml($markdown)
    {
        $cleanHtml = $this->getView()->plugin('cleanHtml');
        if (null === $this->parsedown) {
            $this->parsedown = new \ParsedownExtra();
            $this->parsedown->setBreaksEnabled(true);
        }
        $text = $this->parsedown->text($markdown);
        return $cleanHtml($text);
    }

    /**
     * Converts markdown to html
     *
     * Finna: back-compatibility with default param and call logic
     *
     * @param string $markdown Markdown formatted text
     *
     * @return string
     */
    public function __invoke(string $markdown = null)
    {
        return null === $markdown ? $this : parent::__invoke($markdown);
    }
}
