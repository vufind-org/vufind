<?php

/**
 * VuFind Action Feature Trait - Bulk action helper methods
 * Depends on access to the config loader and export support class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024
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
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Feature;

/**
 * VuFind Action Feature Trait - Bulk action helper methods
 *
 * @category VuFind
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait BulkActionTrait
{
    /**
     * Config
     *
     * @var \Laminas\Config\Config
     */
    protected $bulkActionConfig;

    /**
     * Export Config
     *
     * @var \Laminas\Config\Config
     */
    protected $bulkActionExportConfig;

    /**
     * Get Config.
     *
     * @return \Laminas\Config\Config
     */
    protected function getBulkActionConfig()
    {
        if ($this->bulkActionConfig === null) {
            $this->bulkActionConfig = $this->configLoader->get('config');
        }
        return $this->bulkActionConfig;
    }

    /**
     * Get Export Config.
     *
     * @return \Laminas\Config\Config
     */
    protected function getBulkActionExportConfig()
    {
        if ($this->bulkActionExportConfig === null) {
            $this->bulkActionExportConfig = $this->configLoader->get('export');
        }
        return $this->bulkActionExportConfig;
    }

    /**
     * Get the limit of a bulk action.
     *
     * @param string $action Name of the bulk action
     *
     * @return int
     */
    public function getBulkActionLimit($action)
    {
        if ($action == 'export') {
            $formats = $this->export->getActiveFormats('bulk');
            return max(array_map([$this, 'getExportActionLimit'], $formats));
        }
        $bulkActionConfig = $this->getBulkActionConfig()?->BulkActions;
        return $bulkActionConfig?->limits?->$action
            ?? $bulkActionConfig?->limits?->default
            ?? 100;
    }

    /**
     * Get the limit of the export action for a specific format.
     *
     * @param string $format Name of the format
     *
     * @return int
     */
    public function getExportActionLimit($format)
    {
        $bulkActionConfig = $this->getBulkActionConfig()?->BulkActions;
        return $this->getBulkActionExportConfig()?->$format?->limit
            ?? $bulkActionConfig?->limits?->export
            ?? $bulkActionConfig?->limits?->default
            ?? 100;
    }
}
