<?php

/**
 * "New ILS items" channel provider.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

/**
 * "New ILS items" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NewILSItems extends AbstractILSChannelProvider
{
    /**
     * Channel title (will be run through translator).
     *
     * @var string
     */
    protected $channelTitle = 'New Items';

    /**
     * Retrieve data from the ILS.
     *
     * @return array
     */
    protected function getIlsResponse()
    {
        if ($this->ils->checkCapability('getNewItems')) {
            $response = $this->ils
                ->getNewItems(1, $this->channelSize, $this->maxAge);
            return $response['results'] ?? [];
        }
        return [];
    }

    /**
     * Given one element from the ILS function's response array, extract the
     * ID value.
     *
     * @param array $response Response array
     *
     * @return string
     */
    protected function extractIdsFromResponse($response)
    {
        return $response['id'];
    }
}
