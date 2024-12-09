<?php

/**
 * EuropeanaResultsDeferred Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function is_object;

/**
 * EuropeanaResultsDeferred Recommendations Module
 *
 * This class sets up an AJAX call to trigger a call to the EuropeanaResults
 * module.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedigner@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class EuropeanaResultsDeferred extends AbstractSearchObjectDeferred
{
    /**
     * Number of expected module parameters (from .ini config)
     *
     * @var int
     */
    protected $paramCount = 4;

    /**
     * Initialize the lookFor query parameter. Called from init().
     *
     * @param \VuFind\Search\Base\Params $params   Search parameter object
     * @param \Laminas\Stdlib\Parameters $request  Parameter object representing user
     * request.
     * @param array                      $settings Parameter array (passed by reference)
     *
     * @return void
     */
    protected function initLookFor($params, $request, &$settings)
    {
        // Collect the best possible search term(s):
        $this->lookfor = $request->get('lookfor', '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->getQuery()->getAllTerms();
        }
        $this->lookfor = trim($this->lookfor);
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @return string Module name in call to AjaxHandler
     */
    protected function getAjaxModule()
    {
        return 'EuropeanaResults';
    }
}
