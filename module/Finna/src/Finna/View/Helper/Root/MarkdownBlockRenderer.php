<?php
/**
 * Custom html block renderer for CommonMark
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

use League\CommonMark\Block\Element\AbstractBlock;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\ElementRendererInterface;
use League\CommonMark\Util\RegexHelper;

/**
 * Custom html block renderer for CommonMark
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MarkdownBlockRenderer implements BlockRendererInterface
{
    /**
     * Render the children of html block elements with attribute markdown="1"
     * as inline to enable markdown syntax inside them
     *
     * @param AbstractBlock            $block        block element
     * @param ElementRendererInterface $htmlRenderer html renderer
     * @param bool                     $inTightList  Whether the element is being
     *                                               rendered in a tight list or not
     *
     * @return string
     */
    public function render(
        \League\CommonMark\Block\Element\AbstractBlock $block,
        \League\CommonMark\ElementRendererInterface $htmlRenderer,
        bool $inTightList = false
    ) {
        $openHtmlTag = '/(' . RegexHelper::PARTIAL_OPENBLOCKTAG . ')/';
        if (preg_match($openHtmlTag, $block->getStringContent(), $matches)) {
            if (preg_match('/markdown\=\"1\"/', $matches[1])) {
                return $htmlRenderer->renderInlines($block->children());
            }
        }
        return $block->getStringContent();
    }
}
