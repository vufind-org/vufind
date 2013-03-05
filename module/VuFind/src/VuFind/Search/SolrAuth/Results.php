<?php
/**
 * Solr Authority aspect of the Search Multi-class (Results)
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search_SolrAuth
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\SolrAuth;

/**
 * Solr Authority Search Parameters
 *
 * @category VuFind2
 * @package  Search_SolrAuth
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params Object representing user search
     * parameters.
     */
    public function __construct(\VuFind\Search\Base\Params $params)
    {
        parent::__construct($params);
        $this->backendId = 'SolrAuth';
    }

    /**
     * Get a connection to the Solr index.
     *
     * @param null|array $shards Selected shards to use (null for defaults)
     * @param string     $index  ID of index/search classes to use (this assumes
     * that \VuFind\Search\$index\Options and VuFind\Connection\$index are both
     * valid classes)
     *
     * @return \VuFind\Connection\Solr
     */
    public function getSolrConnection($shards = null, $index = 'SolrAuth')
    {
        return parent::getSolrConnection($shards, $index);
    }
}