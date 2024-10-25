<?php

/**
 * Record driver plugin manager
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use Laminas\ServiceManager\Factory\InvokableFactory;

use function is_callable;

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
        'browzine' => BrowZine::class,
        'eds' => EDS::class,
        'eit' => EIT::class,
        'epf' => EPF::class,
        'libguides' => LibGuides::class,
        'libguidesaz' => LibGuidesAZ::class,
        'missing' => Missing::class,
        'pazpar2' => Pazpar2::class,
        'primo' => Primo::class,
        'search2default' => Search2Default::class,
        'solrarchivesspace' => SolrArchivesSpace::class,
        'solrauth' => SolrAuthMarc::class, // legacy name
        'solrauthdefault' => SolrAuthDefault::class,
        'solrauthmarc' => SolrAuthMarc::class,
        'solrdefault' => SolrDefault::class,
        'solrmarc' => SolrMarc::class,
        'solrmarcremote' => SolrMarcRemote::class,
        'solroverdrive' => SolrOverdrive::class,
        'solrreserves' => SolrReserves::class,
        'solrweb' => SolrWeb::class,
        'summon' => Summon::class,
        'worldcat2' => WorldCat2::class,
    ];

    /**
     * Default delegator factories.
     *
     * @var string[][]|\Laminas\ServiceManager\Factory\DelegatorFactoryInterface[][]
     */
    protected $delegators = [
        SolrMarc::class => [IlsAwareDelegatorFactory::class],
        SolrMarcRemote::class => [IlsAwareDelegatorFactory::class],
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        BrowZine::class => InvokableFactory::class,
        EDS::class => NameBasedConfigFactory::class,
        EIT::class => NameBasedConfigFactory::class,
        EPF::class => NameBasedConfigFactory::class,
        LibGuides::class => InvokableFactory::class,
        LibGuidesAZ::class => InvokableFactory::class,
        Missing::class => AbstractBaseFactory::class,
        Pazpar2::class => NameBasedConfigFactory::class,
        Primo::class => NameBasedConfigFactory::class,
        Search2Default::class => Search2DefaultFactory::class,
        SolrArchivesSpace::class => SolrDefaultFactory::class,
        SolrAuthDefault::class => SolrDefaultWithoutSearchServiceFactory::class,
        SolrAuthMarc::class => SolrDefaultWithoutSearchServiceFactory::class,
        SolrDefault::class => SolrDefaultFactory::class,
        SolrMarc::class => SolrDefaultFactory::class,
        SolrMarcRemote::class => SolrDefaultFactory::class,
        SolrOverdrive::class => SolrOverdriveFactory::class,
        SolrReserves::class => SolrDefaultWithoutSearchServiceFactory::class,
        SolrWeb::class => SolrWebFactory::class,
        Summon::class => SummonFactory::class,
        WorldCat2::class => NameBasedConfigFactory::class,
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
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;

        $this->addAbstractFactory(PluginFactory::class);

        parent::__construct($configOrContainerInstance, $v3config);

        // Add an initializer for setting up hierarchies
        $initializer = function ($sm, $instance) {
            $hasHierarchyType = is_callable([$instance, 'getHierarchyType']);
            if (
                $hasHierarchyType
                && is_callable([$instance, 'setHierarchyDriverManager'])
            ) {
                if ($sm && $sm->has(\VuFind\Hierarchy\Driver\PluginManager::class)) {
                    $instance->setHierarchyDriverManager(
                        $sm->get(\VuFind\Hierarchy\Driver\PluginManager::class)
                    );
                }
            }
        };
        $this->addInitializer($initializer);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return AbstractBase::class;
    }

    /**
     * Convenience method to retrieve a populated Solr record driver.
     *
     * @param array  $data             Raw Solr data
     * @param string $keyPrefix        Record class name prefix
     * @param string $defaultKeySuffix Default key suffix
     *
     * @return AbstractBase
     */
    public function getSolrRecord(
        $data,
        $keyPrefix = 'Solr',
        $defaultKeySuffix = 'Default'
    ) {
        $key = $keyPrefix . ucwords(
            $data['record_format'] ?? $data['recordtype'] ?? $defaultKeySuffix
        );
        $recordType = $this->has($key) ? $key : $keyPrefix . $defaultKeySuffix;

        // Extract highlighting details injected earlier by
        // \VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory
        $hl = $data['__highlight_details'] ?? [];
        unset($data['__highlight_details']);
        // Build the object:
        $driver = $this->get($recordType);
        $driver->setRawData($data);
        $driver->setHighlightDetails($hl);
        return $driver;
    }

    /**
     * Convenience method to retrieve a populated Search2 record driver.
     *
     * @param array  $data             Raw Solr data
     * @param string $defaultKeySuffix Default key suffix
     *
     * @return AbstractBase
     */
    public function getSearch2Record($data, $defaultKeySuffix = 'Default')
    {
        return $this->getSolrRecord($data, 'Search2', $defaultKeySuffix);
    }

    /**
     * Convenience method to retrieve a populated Solr authority record driver.
     *
     * @param array $data Raw Solr data
     *
     * @return AbstractBase
     */
    public function getSolrAuthRecord($data)
    {
        return $this->getSolrRecord($data, 'SolrAuth');
    }
}
