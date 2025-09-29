<?php

/**
 * Solr Reserves aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_SolrReserves
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\SolrReserves;

/**
 * Solr Reserves Search Parameters
 *
 * @category VuFind
 * @package  Search_SolrReserves
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'SolrReserves';
}
