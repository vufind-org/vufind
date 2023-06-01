<?php

/**
 * "Retrieve search params" view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Search\Params\PluginManager;

/**
 * "Retrieve search params" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchParams extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param PluginManager $manager Search manager
     */
    public function __construct(PluginManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Wrapper to the options plugin manager
     *
     * @param string $type The search type of the object to retrieve
     *
     * @return \VuFind\Search\Base\Options
     */
    public function __invoke($type = 'Solr')
    {
        return $this->manager->get($type);
    }
}
