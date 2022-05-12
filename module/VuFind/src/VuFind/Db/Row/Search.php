<?php
/**
 * Row Definition for search
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Row;

use VuFind\Crypt\HMAC;

/**
 * Row Definition for search
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property int     $user_id
 * @property ?string $session_id
 * @property ?int    $folder_id
 * @property string  $created
 * @property ?string $title
 * @property int     $saved
 * @property string  $search_object
 * @property ?int    $checksum
 * @property int     $notification_frequency
 * @property string  $last_notification_sent
 * @property string  $notification_base_url
 */
class Search extends RowGateway
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'search', $adapter);
    }

    /**
     * Support method to make sure that the search_object field is formatted as a
     * string, since PostgreSQL sometimes represents it as a resource.
     *
     * @return void
     */
    protected function normalizeSearchObject()
    {
        // Note that if we have a resource, we need to grab the contents before
        // saving -- this is necessary for PostgreSQL compatibility although MySQL
        // returns a plain string
        if (is_resource($this->search_object)) {
            $this->search_object = stream_get_contents($this->search_object);
        }
    }

    /**
     * Get the search object from the row
     *
     * @return \VuFind\Search\Minified
     */
    public function getSearchObject()
    {
        // We need to make sure the search object is a string before unserializing:
        $this->normalizeSearchObject();
        $result = unserialize($this->search_object);
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
        // We can't save if the search object is a resource; make sure it's a
        // string first:
        $this->normalizeSearchObject();
        return parent::save();
    }

    /**
     * Set last executed time for scheduled alert.
     *
     * @param string $time Time.
     *
     * @return mixed
     */
    public function setLastExecuted($time)
    {
        $this->last_notification_sent = $time;
        return $this->save();
    }

    /**
     * Set schedule for scheduled alert.
     *
     * @param int    $schedule Schedule.
     * @param string $url      Site base URL
     *
     * @return mixed
     */
    public function setSchedule($schedule, $url = null)
    {
        $this->notification_frequency = $schedule;
        if ($url) {
            $this->notification_base_url = $url;
        }
        return $this->save();
    }

    /**
     * Utility function for generating a token for unsubscribing a
     * saved search.
     *
     * @param VuFind\Crypt\HMAC $hmac HMAC hash generator
     * @param object            $user User object
     *
     * @return string token
     */
    public function getUnsubscribeSecret(HMAC $hmac, $user)
    {
        $data = [
            'id' => $this->id,
            'user_id' => $user->id,
            'created' => $user->created
        ];
        return $hmac->generate(array_keys($data), $data);
    }
}
