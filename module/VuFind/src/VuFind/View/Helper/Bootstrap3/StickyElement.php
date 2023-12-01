<?php

/**
 * Helper class for managing bootstrap theme's sticky elements.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Bootstrap3;

use function in_array;

/**
 * Helper class for managing bootstrap theme's sticky elements.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class StickyElement extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Names of sticky elements
     *
     * @var array
     */
    protected $stickyElements;

    /**
     * Constructor
     *
     * @param array $stickyElements Names of the element that shall be sticky
     */
    public function __construct($stickyElements = [])
    {
        $this->stickyElements = $stickyElements;
    }

    /**
     * Checks if any sticky elements are configured.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return !empty($this->stickyElements);
    }

    /**
     * Returns the sticky-element class if the element is configured and
     * optionally adds the position attribute.
     *
     * @param string $elementName Name of the element
     * @param ?int   $pos         Sticky position of the element
     *
     * @return array
     */
    public function getElementAttributes($elementName, ?int $pos = null)
    {
        $elementIsEnabled = in_array($elementName, $this->stickyElements);
        return [
            'class' => $elementIsEnabled ? 'sticky-element' : '',
            'posAttr' => $elementIsEnabled && $pos !== null ? 'data-sticky-pos="' . $pos . '"' : '',
        ];
    }
}
