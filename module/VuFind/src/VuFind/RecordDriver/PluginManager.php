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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Record driver plugin manager
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Constructor
     *
     * @param null|ConfigInterface $configuration Configuration settings (optional)
     */
    public function __construct(
        \Zend\ServiceManager\ConfigInterface $configuration = null
    ) {
        parent::__construct($configuration);

        // Add an initializer for setting up hierarchies
        $initializer = function ($instance, $manager) {
            $hasHierarchyType = is_callable(array($instance, 'getHierarchyType'));
            if ($hasHierarchyType
                && is_callable(array($instance, 'setHierarchyDriverManager'))
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

        // Record drivers are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->setShareByDefault(false);
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
        $key = 'Solr' . ucwords($data['recordtype']);
        $recordType = $this->has($key) ? $key : 'SolrDefault';

        // Build the object:
        $driver = $this->get($recordType);
        $driver->setRawData($data);
        return $driver;
    }
}