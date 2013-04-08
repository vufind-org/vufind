<?php
/**
 * Record loader
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Record;
use Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader implements ServiceLocatorAwareInterface
{
    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Given a record source, return the search object that can load that type of
     * record.
     *
     * @param string $source Record source
     *
     * @throws \Exception
     * @return \VuFind\Search\Base\Results
     */
    protected function getClassForSource($source)
    {
        // Legacy hack: translate "VuFind" source from database into "Solr":
        if ($source == 'VuFind') {
            $source = 'Solr';
        }
        return $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
            ->get($source);
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = 'VuFind')
    {
        // Load the record:
        return $this->getClassForSource($source)->getRecord($id);
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch($ids)
    {
        // Sort the IDs by source -- we'll create an associative array indexed by
        // source and record ID which points to the desired position of the indexed
        // record in the final return array:
        $idBySource = array();
        foreach ($ids as $i => $details) {
            // Convert source|id string to array if necessary:
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $ids[$i] = $details = array(
                    'source' => $parts[0], 'id' => $parts[1]
                );
            }
            $idBySource[$details['source']][$details['id']] = $i;
        }

        // Retrieve the records and put them back in order:
        $retVal = array();
        foreach ($idBySource as $source => $details) {
            $records = $this->getClassForSource($source)
                ->getRecords(array_keys($details));
            foreach ($records as $current) {
                $id = $current->getUniqueId();
                $retVal[$details[$id]] = $current;
            }
        }

        // Check for missing records and fill gaps with \VuFind\RecordDriver\Missing
        // objects:
        foreach ($ids as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $fields = isset($details['extra_fields'])
                    ? $details['extra_fields'] : array();
                $fields['id'] = $details['id'];
                $factory = $this->getServiceLocator()
                    ->get('VuFind\RecordDriverPluginManager');
                $retVal[$i] = $factory->get('Missing');
                $retVal[$i]->setRawData($fields);
                $retVal[$i]->setResourceSource($details['source']);
            }
        }

        // Send back the final array, with the keys in proper order:
        ksort($retVal);
        return $retVal;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Manager
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}
