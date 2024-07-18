<?php

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindSearch\Backend\WorldCat2;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFindSearch\ParamBag;

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * @category VuFind
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Connector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Fake record for simulation purposes.
     * TODO: delete when no longer needed.
     *
     * @var array
     */
    protected $fakeRecord = [
        'id' => 'foo',
        'title' => 'Fake simulated record',
        'title_short' => 'Fake simulated record',
        'title_full' => 'Fake simulated record',
    ];

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client  An HTTP client object
     * @param array                $options Additional config settings
     */
    public function __construct(
        protected \Laminas\Http\Client $client,
        protected array $options = []
    ) {
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return string    MARC XML
     */
    public function getRecord($id, ParamBag $params = null)
    {
        // TODO: implement something real here.
        $this->debug("Fetching record $id");
        $error = false;
        $body = $this->fakeRecord;
        return [
            'docs' => $error ? [] : [$body],
            'offset' => 0,
            'total' => $error ? 0 : 1,
        ];
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return string
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        // TODO: implement something real here.
        $docs = [$this->fakeRecord];
        $total = 1;
        return compact('docs', 'offset', 'total');
    }
}
