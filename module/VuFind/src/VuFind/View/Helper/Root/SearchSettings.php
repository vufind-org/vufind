<?php

/**
 * Search settings view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;

/**
 * Search settings view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchSettings extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * VuFind configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Search params
     *
     * @var Params
     */
    protected $params = null;

    /**
     * Constructor
     *
     * @param array $config VuFind configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Store params and return this object
     *
     * @param Params $params Search params
     *
     * @return SearchResults
     */
    public function __invoke(Params $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Check if bulk options are enabled
     *
     * @return bool
     */
    public function bulkOptionsEnabled()
    {
        if (!($this->config['Site']['showBulkOptions'] ?? false)) {
            return false;
        }
        return $this->getOptions()->supportsCart();
    }

    /**
     * Check if cart controls are enabled
     *
     * @return bool
     */
    public function cartControlsEnabled()
    {
        $cart = $this->view->plugin('cart');
        return
            $this->getOptions()->supportsCart()
            && $cart()->isActive()
            && ($this->bulkOptionsEnabled() || !$cart()->isActiveInSearch());
    }

    /**
     * Check if result selection checkboxes are enabled
     *
     * @return bool
     */
    public function checkboxesEnabled()
    {
        return $this->bulkOptionsEnabled() || $this->cartControlsEnabled();
    }

    /**
     * Get search options from params
     *
     * @return Options
     */
    protected function getOptions(): Options
    {
        if (null === $this->params) {
            throw new \Exception('Params not provided for SearchSettings helper');
        }
        return $this->params->getOptions();
    }
}
