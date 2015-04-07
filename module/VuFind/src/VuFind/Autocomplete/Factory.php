<?php
/**
 * Factory for autocomplete plugins.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Autocomplete;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for autocomplete plugins.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Solr plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSolr(ServiceManager $sm)
    {
        return new Solr(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Construct the SolrAuth plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrAuth
     */
    public static function getSolrAuth(ServiceManager $sm)
    {
        return new SolrAuth(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Construct the SolrCN (call number) plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrCN
     */
    public static function getSolrCN(ServiceManager $sm)
    {
        return new SolrCN(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Construct the SolrReserves plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrReserves
     */
    public static function getSolrReserves(ServiceManager $sm)
    {
        return new SolrReserves(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }
}