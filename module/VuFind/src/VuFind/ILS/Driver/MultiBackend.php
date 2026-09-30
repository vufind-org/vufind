<?php

/**
 * Multiple Backend Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2012-2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use function strlen;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class MultiBackend extends AbstractMappingDriver
{
    /**
     * Param mapping for MultiBackend ILLRequestIsValid method that does not map the patron.
     *
     * @param array $params Params
     *
     * @return array
     */
    protected function checkILLRequestIsValidParamMap($params)
    {
        $source = $this->getSourceForRecordId($params[0] ?? '');
        // Patron is not mapped so that the correct library can be determined
        $params[0] = $this->mapVuFindRecordIdToIlsRecordId($params[0] ?? '', $source);
        $params[1] = $this->mapVuFindHoldToIlsHold($params[1] ?? [], $source);
        return [$params, $source];
    }

    /**
     * Constructor.
     *
     * @param \VuFind\Config\ConfigManagerInterface $configManager Configuration manager
     * @param \VuFind\Auth\ILSAuthenticator         $ilsAuth       ILS authenticator
     * @param PluginManager                         $driverManager ILS driver manager
     */
    public function __construct(
        \VuFind\Config\ConfigManagerInterface $configManager,
        \VuFind\Auth\ILSAuthenticator $ilsAuth,
        PluginManager $driverManager
    ) {
        parent::__construct($configManager, $ilsAuth, $driverManager);

        // Patron is not mapped for ILL so that the correct library can be determined
        $this->paramMapAndSourceCheckMethods['checkILLRequestIsValid'] = 'checkILLRequestIsValidParamMap';
        $this->paramMapAndSourceCheckMethods['getILLPickupLibraries'] = 'recordIdParamMap';
        $this->paramMapAndSourceCheckMethods['getILLPickupLocations'] = 'recordIdParamMap';
        $this->paramMapAndSourceCheckMethods['placeILLRequest'] = 'detailsParamMap';
    }

    /**
     * Get available login targets (drivers enabled for login).
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        return $this->config['Login']['drivers'] ?? [];
    }

    /**
     * Get default login driver.
     *
     * @return string Default login driver or empty string
     */
    public function getDefaultLoginDriver()
    {
        if (isset($this->config['Login']['default_driver'])) {
            return $this->config['Login']['default_driver'];
        }
        $drivers = $this->getLoginDrivers();
        if ($drivers) {
            return $drivers[0];
        }
        return '';
    }

    /**
     * Map VuFind's record id to the record id of the ILS.
     *
     * @param string $recordId VuFind Record ID
     * @param string $source   Source code
     *
     * @return string ILS Record ID
     */
    protected function mapVuFindRecordIdToIlsRecordId($recordId, $source)
    {
        return $this->mapVuFindIdToIlsId($recordId, $source);
    }

    /**
     * Map VuFind's record id to the record id of the ILS.
     *
     * @param string $recordId VuFind Record ID
     * @param string $source   Source code
     *
     * @return string ILS Record ID
     */
    protected function mapVuFindItemIdToIlsItemId($recordId, $source)
    {
        return $this->mapVuFindIdToIlsId($recordId, $source);
    }

    /**
     * Map VuFind's cat_username to the cat_username of the ILS.
     *
     * @param string $catUsername VuFind cat_username
     * @param string $source      Source code
     *
     * @return string ILS cat_username
     */
    protected function mapVuFindCatUsernameToIlsCatUsername(string $catUsername, $source): string
    {
        return $this->mapVuFindIdToIlsId($catUsername, $source);
    }

    /**
     * Map VuFind ids to the ids of the ILS.
     *
     * @param string $id     VuFind ID
     * @param string $source Source code
     *
     * @return string ILS ID
     */
    protected function mapVuFindIdToIlsId(string $id, $source): string
    {
        if (str_starts_with($id, $source . '.')) {
            return substr($id, strlen($source) + 1);
        }
        $this->debug("Could not find local id in '$id'");
        return $id;
    }

    /**
     * Map VuFind's record id to the record id of the ILS.
     *
     * @param string $recordId VuFind Record ID
     * @param string $source   Source code
     *
     * @return string ILS Record ID
     */
    protected function mapIlsRecordIdToVuFindRecordId($recordId, $source)
    {
        return $this->mapIlsIdToVuFindId($recordId, $source);
    }

    /**
     * Map VuFind's record id to the record id of the ILS.
     *
     * @param string $itemId VuFind Record ID
     * @param string $source Source code
     *
     * @return string ILS Record ID
     */
    protected function mapIlsItemIdToVuFindItemId($itemId, $source)
    {
        return $this->mapIlsIdToVuFindId($itemId, $source);
    }

    /**
     * Map VuFind's cat_username to the cat_username id of the ILS.
     *
     * @param string $catUsername VuFind Patron ID
     * @param string $source      Source code
     *
     * @return string ILS Patron ID
     */
    protected function mapIlsCatUsernameToVuFindCatUsername(string $catUsername, $source): string
    {
        return $this->mapIlsIdToVuFindId($catUsername, $source);
    }

    /**
     * Map VuFind ids to the ids of the ILS.
     *
     * @param string $id     VuFind ID
     * @param string $source Source code
     *
     * @return string ILS ID
     */
    protected function mapIlsIdToVuFindId(string $id, $source): string
    {
        return (!empty($source) ? $source . '.' : '') . $id;
    }

    /**
     * Extract source from the given record ID.
     *
     * @param string $recordId Record global ID's to local ID's in the given array.id
     *
     * @return string Source
     */
    protected function getSourceForRecordId($recordId)
    {
        return $this->getSource($recordId);
    }

    /**
     * Extract source from the given item ID.
     *
     * @param string $itemId Record global ID's to local ID's in the given array.id
     *
     * @return string Source
     */
    protected function getSourceForItemId($itemId)
    {
        return $this->getSource($itemId);
    }

    /**
     * Extract source from the given catalog username.
     *
     * @param string $catUsername Catalog username
     *
     * @return string Source
     */
    protected function getSourceForCatUsername($catUsername)
    {
        return $this->getSource($catUsername);
    }

    /**
     * Extract source from the given ID.
     *
     * @param string $id The id to be split
     *
     * @return string Source
     */
    protected function getSource($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }

        return '';
    }
}
