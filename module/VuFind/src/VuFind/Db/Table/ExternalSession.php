<?php

/**
 * Table Definition for external_session
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ExternalSessionServiceInterface;

/**
 * Table Definition for external_session
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExternalSession extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;
    use ExpirationTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'external_session'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Add a mapping between local and external session id's
     *
     * @param string $localSessionId    Local (VuFind) session id
     * @param string $externalSessionId External session id
     *
     * @return void
     *
     * @deprecated Use ExternalSessionServiceInterface::addSessionMapping()
     */
    public function addSessionMapping($localSessionId, $externalSessionId)
    {
        $this->getDbService(ExternalSessionServiceInterface::class)
            ->addSessionMapping($localSessionId, $externalSessionId);
    }

    /**
     * Retrieve an object from the database based on an external session ID
     *
     * @param string $sid External session ID to retrieve
     *
     * @return ?\VuFind\Db\Row\ExternalSession
     *
     * @deprecated Use ExternalSessionServiceInterface::getAllByExternalSessionId()
     */
    public function getByExternalSessionId($sid)
    {
        $sessions = $this->getDbService(ExternalSessionServiceInterface::class)->getAllByExternalSessionId($sid);
        return $sessions[0] ?? null;
    }

    /**
     * Destroy data for the given session ID.
     *
     * @param string $sid Session ID to erase
     *
     * @return void
     *
     * @deprecated Use ExternalSessionServiceInterface::destroySession()
     */
    public function destroySession($sid)
    {
        $this->getDbService(ExternalSessionServiceInterface::class)->destroySession($sid);
    }

    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select    Select clause
     * @param string $dateLimit Date threshold of an "expired" record in format
     * 'Y-m-d H:i:s'.
     *
     * @return void
     */
    protected function expirationCallback($select, $dateLimit)
    {
        $select->where->lessThan('created', $dateLimit);
    }
}
