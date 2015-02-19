<?php
/**
 * Statistics Driver Factory Class
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
 * @package  Statistics
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Statistics\Driver;
use Zend\ServiceManager\ServiceManager;

/**
 * Statistics Driver Factory Class
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for File driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return File
     */
    public static function getFile(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $folder = isset($config->Statistics->file)
            ? $config->Statistics->file : sys_get_temp_dir();
        return new File($folder);
    }

    /**
     * Factory for Solr driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSolr(ServiceManager $sm)
    {
        return new Solr(
            $sm->getServiceLocator()->get('VuFind\Solr\Writer'),
            $sm->getServiceLocator()->get('VuFind\Search\BackendManager')
                ->get('SolrStats')
        );
    }
}