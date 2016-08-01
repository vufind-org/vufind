<?php
/**
 * Record driver plugin manager
 *
 * PHP version 5
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use Zend\ServiceManager\ConfigInterface;

/**
 * Record driver plugin manager
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Constructor
     *
     * @param ConfigInterface $configuration Configuration settings (optional)
     */
    public function __construct(ConfigInterface $configuration = null)
    {
        // Record drivers are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->setShareByDefault(false);

        parent::__construct($configuration);

        // Add an initializer for setting up hierarchies
        $initializer = function ($instance, $manager) {
            $hasHierarchyType = is_callable([$instance, 'getHierarchyType']);
            if ($hasHierarchyType
                && is_callable([$instance, 'setHierarchyDriverManager'])
            ) {
                $sm = $manager->getServiceLocator();
                if ($sm && $sm->has('VuFind\HierarchyDriverPluginManager')) {
                    $instance->setHierarchyDriverManager(
                        $sm->get('VuFind\HierarchyDriverPluginManager')
                    );
                }
            }
        };
        $this->addInitializer($initializer, false);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\RecordDriver\AbstractBase';
    }

    /**
     * Convenience method to retrieve a populated Solr record driver.
     *
     * @param array $data Raw Solr data
     *
     * @return AbstractBase
     */
    public function getSolrRecord($data)
    {
        if (isset($data['recordtype'])) {
            $key = 'Solr' . ucwords($data['recordtype']);
            $recordType = $this->has($key) ? $key : 'SolrDefault';
        } else {
            $recordType = 'SolrDefault';
        }

        // Build the object:
        $driver = $this->get($recordType);
        $driver->setRawData($data);
        return $driver;
    }
}
