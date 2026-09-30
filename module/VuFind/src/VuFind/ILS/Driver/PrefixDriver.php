<?php

/**
 * Prefix Driver.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function strlen;

/**
 * Prefix Driver.
 *
 * This driver allows to map IDs in VuFind with prefixes to IDs without those prefixes in the ILS.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class PrefixDriver extends AbstractMappingDriver
{
    /**
     * Record ID prefix.
     *
     * @var string
     */
    protected string $recordIdPrefix;

    /**
     * Item ID prefix.
     *
     * @var string
     */
    protected string $itemIdPrefix;

    /**
     * Catalog username prefix.
     *
     * @var string
     */
    protected string $catUsernamePrefix;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        parent::init();
        if (!($this->defaultDriver = $this->config['General']['default_driver'] ?? false)) {
            throw new ILSException('Default driver needs to be set.');
        }
        $this->recordIdPrefix = $this->config['General']['record_id_prefix'] ?? '';
        $this->itemIdPrefix = $this->config['General']['item_id_prefix'] ?? '';
        $this->catUsernamePrefix = $this->config['General']['cat_username_prefix'] ?? '';
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
        return $this->stripIdPrefix($recordId, $this->recordIdPrefix);
    }

    /**
     * Map VuFind's item id to the item id of the ILS.
     *
     * @param string $itemId VuFind item ID
     * @param string $source Source code
     *
     * @return string ILS item ID
     */
    protected function mapVuFindItemIdToIlsItemId($itemId, $source)
    {
        return $this->stripIdPrefix($itemId, $this->itemIdPrefix);
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
        return $this->stripIdPrefix($catUsername, $this->catUsernamePrefix);
    }

    /**
     * Strip id prefix.
     *
     * @param string $id     VuFind ID
     * @param string $prefix Prefix
     *
     * @return string ILS ID
     */
    protected function stripIdPrefix(string $id, $prefix): string
    {
        if (str_starts_with($id, $prefix)) {
            return substr($id, strlen($prefix));
        }
        $this->debug("Id '$id' does not have prefix '$prefix'");
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
        return $this->addIdPrefix($recordId, $this->recordIdPrefix);
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
        return $this->addIdPrefix($itemId, $this->itemIdPrefix);
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
        return $this->addIdPrefix($catUsername, $this->itemIdPrefix);
    }

    /**
     * Add prefix to id.
     *
     * @param string $id     VuFind ID
     * @param string $prefix Prefix
     *
     * @return string ILS ID
     */
    protected function addIdPrefix(string $id, $prefix): string
    {
        return $prefix . $id;
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
        return $this->defaultDriver;
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
        return $this->defaultDriver;
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
        return $this->defaultDriver;
    }
}
