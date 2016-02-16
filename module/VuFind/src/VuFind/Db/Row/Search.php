<?php
/**
 * Row Definition for search
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Row;

/**
 * Row Definition for search
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends RowGateway
{
    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'search', $adapter);
    }

    /**
     * Get the search object from the row
     *
     * @return \VuFind\Search\Minified
     */
    public function getSearchObject()
    {
        // Resource check for PostgreSQL compatibility:
        $raw = is_resource($this->search_object)
            ? stream_get_contents($this->search_object) : $this->search_object;
        // Some search objects may be Base64-encoded; if so, they're flagged
        // with a prefix so we know how to decode them:
        if (substr($raw, 0, 5) == 'B64__') {
            $raw = base64_decode(substr($raw, 5));
        }
        $result = unserialize($raw);
        if (!($result instanceof \VuFind\Search\Minified)) {
            throw new \Exception('Problem decoding saved search');
        }
        return $result;
    }

    /**
     * Save
     *
     * @return int
     */
    public function save()
    {
        // Note that if we have a resource, we need to grab the contents before
        // saving -- this is necessary for PostgreSQL compatibility although MySQL
        // returns a plain string
        if (is_resource($this->search_object)) {
            $this->search_object = stream_get_contents($this->search_object);
        }
        // Due to inconsistent encoding standards for bytea fields in different
        // versions of PostgreSQL, we need to base64-encode serialized objects
        // before storing them to avoid errors caused by special characters such
        // as backslashes.
        if ('PostgreSQL' == $this->sql->getAdapter()->getPlatform()->getName()) {
            $this->search_object = 'B64__' . base64_encode($this->search_object);
        }
        parent::save();
    }
}
