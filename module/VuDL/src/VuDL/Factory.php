<?php
/**
 * Factory for VuDL resources.
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
 * @package  VuDL
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuDL;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for VuDL resources.
 *
 * @category VuFind2
 * @package  VuDL
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Connection Manager service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Fedora
     */
    public static function getConnectionManager(ServiceManager $sm)
    {
        return new \VuDL\Connection\Manager(
            ['Solr', 'Fedora'], $sm
        );
    }
        
    /**
     * Construct the Connection Fedora service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Fedora
     */
    public static function getConnectionFedora(ServiceManager $sm)
    {
        return new \VuDL\Connection\Fedora(
            $sm->get('VuFind\Config')->get('VuDL')
        );
    }
        
    /**
     * Construct the Connection Solr service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Fedora
     */
    public static function getConnectionSolr(ServiceManager $sm)
    {
        return new \VuDL\Connection\Solr(
            $sm->get('VuFind\Config')->get('VuDL'),
            $sm->get('VuFind\Search\BackendManager')->get('Solr')
        );
    }

    /**
     * Construct the VuDL record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordDriver\SolrVudl
     */
    public static function getRecordDriver(ServiceManager $sm)
    {
        $driver = new RecordDriver\SolrVudl(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->setVuDLConfig(
            $sm->getServiceLocator()->get('VuFind\Config')->get('VuDL')
        );
        return $driver;
    }
}