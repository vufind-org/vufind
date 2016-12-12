<?php
/**
 * Record loader
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Record;
use VuFind\RecordDriver\PluginManager as RecordFactory,
    VuFindSearch\Service as SearchService,
    VuFind\Record\Cache,
    Finna\Db\Table\Resource;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader extends \VuFind\Record\Loader
{
    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id              Record ID
     * @param string $source          Record source
     * @param bool   $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false
    ) {
        if ($source == 'MetaLib') {
            if ($tolerateMissing) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $id]);
                $record->setSourceIdentifier($source);
                return $record;
            }
            throw new RecordMissingException(
                'Record ' . $source . ':' . $id . ' does not exist.'
            );
        }
        return parent::load($id, $source);
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
        // Separate MetaLib ids that are loaded separately
        $loadIds = $metalibIds = $recIds = [];
        foreach ($ids as $key => $data) {
            if (!is_array($data)) {
                $parts = explode('|', $data, 2);
                $data = ['source' => $parts[0], 'id' => $parts[1]];
            }
            $recId = $data['id'];
            $metalib = isset($data['source']) && $data['source'] == 'MetaLib';
            if ($metalib) {
                $metalibIds[] = $recId;
            } else {
                $loadIds[] = $data;
            }
            $recIds[] = $recId;
        }

        $result = [];

        $records = parent::loadBatch($loadIds);

        $metalibIds = array_flip($metalibIds);
        foreach ($recIds as $recId) {
            if (isset($metalibIds[$recId])) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $recId]);
                $record->setSourceIdentifier('MetaLib');
                $result[] = $record;
            } else {
                $result[] = array_shift($records);
            }
        }

        return $result;
    }
}
