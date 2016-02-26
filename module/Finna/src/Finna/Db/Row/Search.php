<?php
/**
 * Row Definition for search
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Db_Row
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Row;
use VuFind\Crypt\HMAC;

/**
 * Row Definition for search
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends \VuFind\Db\Row\Search
{
    /**
     * Set last executed time for scheduled alert.
     *
     * @param DateTime $time Time.
     *
     * @return mixed
     */
    public function setLastExecuted($time)
    {
        $this->finna_last_executed = $time;
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
    public function setSchedule($schedule, $url)
    {
        $this->finna_schedule = $schedule;
        $this->finna_schedule_base_url = $url;
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

    /**
     * Get the search object from the row
     *
     * @param boolean $parent True to return search_object (VuFind)
     * even when finna_search_object is available.
     *
     * @return \VuFind\Search\Minified
     */
    public function getSearchObject($parent = false)
    {
        $parentSO = parent::getSearchObject();
        if ($parent) {
            return $parentSO;
        }

        // Resource check for PostgreSQL compatibility:
        $raw = is_resource($this->finna_search_object)
            ? stream_get_contents($this->finna_search_object)
            : $this->finna_search_object;
        if ($raw) {
            $so = unserialize($raw);
            $so->setParentSO($parentSO);
            return $so;
        } else {
            return $parentSO;
        }
    }
}
