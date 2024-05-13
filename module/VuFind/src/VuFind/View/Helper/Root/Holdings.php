<?php

/**
 * View helper to support ILS holdings display
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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

use function strlen;

/**
 * View helper to support ILS holdings display
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holdings extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Is the provided holdings array (from an ILS driver's getHolding method)
     * suitable for display to the end user?
     *
     * @param array $holding Holding to evaluate
     *
     * @return bool
     */
    public function holdingIsVisible(array $holding): bool
    {
        $catalogConfig = $this->config['Catalog'] ?? [];
        $showEmptyBarcodes
            = (bool)($catalogConfig['display_items_without_barcodes'] ?? true);
        return $holding['availability']->isVisibleInHoldings()
            && ($showEmptyBarcodes || strlen($holding['barcode'] ?? '') > 0);
    }
}
