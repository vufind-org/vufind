<?php

/**
 * Abstract base for deferred-load Summon recommendations modules
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014.
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
 * Abstract base for deferred-load Summon recommendations modules
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedigner@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class AbstractSummonRecommendDeferred extends AbstractSearchObjectDeferred
{
    /**
     * Number of expected module parameters (from .ini config)
     *
     * @var int
     */
    protected $paramCount = 1;

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
        $lookforParam = empty($settings[0]) ? 'lookfor' : $settings[0];
        $this->lookfor = $request->get($lookforParam, '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->getQuery()->getAllTerms();
        }
        $this->lookfor = trim($this->lookfor);

        // In AJAX mode, the query will always be found in the 'lookfor' parameter,
        // so override the setting:
        $settings[0] = 'lookfor';
    }
}
