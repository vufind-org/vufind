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
     * Checks if an elements shall be sticky.
     *
     * @param string $elementName Name of the element
     *
     * @return string CSS classes to apply
     */
    public function __invoke($elementName)
    {
        return in_array($elementName, $this->stickyElements) ? 'sticky-element' : '';
    }
}
