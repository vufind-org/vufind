<?php
/**
 * Helper for creating HtmlAttributesSet objects.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\View\HtmlAttributesSet;
use Laminas\View\Helper\AbstractHelper;

/**
 * Helper for creating HtmlAttributesSet objects.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HtmlAttributes extends AbstractHelper
{
    /**
     * Returns a new HtmlAttributesSet object, optionally initializing it with
     * the provided value.
     *
     * @param iterable $attributes Attributes
     *
     * @return HtmlAttributesSet
     */
    public function __invoke($attributes = [])
    {
        return new HtmlAttributesSet(
            $this->getView()->plugin('escapehtml')->getEscaper(),
            $this->getView()->plugin('escapehtmlattr')->getEscaper(),
            $attributes
        );
    }
}
