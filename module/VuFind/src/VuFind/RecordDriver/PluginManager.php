<?php
/**
 * Record driver plugin manager
 *
 * PHP version 7
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
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'browzine' => 'VuFind\RecordDriver\BrowZine',
        'eds' => 'VuFind\RecordDriver\EDS',
        'eit' => 'VuFind\RecordDriver\EIT',
        'libguides' => 'VuFind\RecordDriver\LibGuides',
        'missing' => 'VuFind\RecordDriver\Missing',
        'pazpar2' => 'VuFind\RecordDriver\Pazpar2',
        'primo' => 'VuFind\RecordDriver\Primo',
        'solrauth' => 'VuFind\RecordDriver\SolrAuth',
        'solrdefault' => 'VuFind\RecordDriver\SolrDefault',
        'solrmarc' => 'VuFind\RecordDriver\SolrMarc',
        'solrmarcremote' => 'VuFind\RecordDriver\SolrMarcRemote',
        'solrreserves' => 'VuFind\RecordDriver\SolrReserves',
        'solrweb' => 'VuFind\RecordDriver\SolrWeb',
        'summon' => 'VuFind\RecordDriver\Summon',
        'worldcat' => 'VuFind\RecordDriver\WorldCat',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\RecordDriver\BrowZine' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\RecordDriver\EDS' => 'VuFind\RecordDriver\Factory::getEDS',
        'VuFind\RecordDriver\EIT' => 'VuFind\RecordDriver\Factory::getEIT',
        'VuFind\RecordDriver\LibGuides' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\RecordDriver\Missing' => 'VuFind\RecordDriver\Factory::getMissing',
        'VuFind\RecordDriver\Pazpar2' => 'VuFind\RecordDriver\Factory::getPazpar2',
        'VuFind\RecordDriver\Primo' => 'VuFind\RecordDriver\Factory::getPrimo',
        'VuFind\RecordDriver\SolrAuth' => 'VuFind\RecordDriver\Factory::getSolrAuth',
        'VuFind\RecordDriver\SolrDefault' =>
            'VuFind\RecordDriver\Factory::getSolrDefault',
        'VuFind\RecordDriver\SolrMarc' => 'VuFind\RecordDriver\Factory::getSolrMarc',
        'VuFind\RecordDriver\SolrMarcRemote' =>
            'VuFind\RecordDriver\Factory::getSolrMarcRemote',
        'VuFind\RecordDriver\SolrReserves' =>
            'VuFind\RecordDriver\Factory::getSolrReserves',
        'VuFind\RecordDriver\SolrWeb' => 'VuFind\RecordDriver\Factory::getSolrWeb',
        'VuFind\RecordDriver\Summon' => 'VuFind\RecordDriver\Factory::getSummon',
        'VuFind\RecordDriver\WorldCat' => 'VuFind\RecordDriver\Factory::getWorldCat',
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;

        $this->addAbstractFactory('VuFind\RecordDriver\PluginFactory');

        parent::__construct($configOrContainerInstance, $v3config);

        // Add an initializer for setting up hierarchies
        $initializer = function ($sm, $instance) {
            $hasHierarchyType = is_callable([$instance, 'getHierarchyType']);
            if ($hasHierarchyType
                && is_callable([$instance, 'setHierarchyDriverManager'])
            ) {
                if ($sm && $sm->has('VuFind\Hierarchy\Driver\PluginManager')) {
                    $instance->setHierarchyDriverManager(
                        $sm->get('VuFind\Hierarchy\Driver\PluginManager')
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
