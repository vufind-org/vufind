<?php
/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
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
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for missing records -- used for saved favorites that have been deleted
 * from the index.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
class Missing extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param array  $data   Raw data from the Solr index representing the record;
     * Solr Record Model objects are normally constructed by Solr Record Driver
     * objects using data passed in from a Solr Search Results object.
     * @param string $source The source of the missing record (for proper linking).
     */
    public function __construct($data = null, $source = 'VuFind')
    {
        if (!is_array($data)) {
            $data = array();
        }
        parent::__construct($data);
        $this->resourceSource = $source;
    }

    /**
     * Get the name of the route used to build links to URLs representing the record.
     *
     * @return string
     */
    public function getRecordRoute()
    {
        return 'missingrecord';
    }
}
