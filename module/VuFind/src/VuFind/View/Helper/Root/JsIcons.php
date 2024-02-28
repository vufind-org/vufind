<?php

/**
 * JsIcons helper for passing icon HTML to Javascript
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * JsIcons helper for passing icon HTML to Javascript
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class JsIcons extends AbstractJsStrings
{
    /**
     * Icon helper
     *
     * @var Icon
     */
    protected $iconHelper;

    /**
     * Constructor
     *
     * @param Icon   $iconHelper Icon helper
     * @param string $varName    Variable name to store icons
     */
    public function __construct(
        Icon $iconHelper,
        $varName = 'vufindIconString'
    ) {
        parent::__construct($varName);
        $this->iconHelper = $iconHelper;
    }

    /**
     * Generate Icon from string
     *
     * @param string $icon String to transform
     * @param string $key  JSON object key
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function mapValue($icon, string $key): string
    {
        return ($this->iconHelper)($icon);
    }
}
