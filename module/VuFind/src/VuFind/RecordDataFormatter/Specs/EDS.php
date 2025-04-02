<?php

/**
 * EDS RecordDataFormatter specs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace VuFind\RecordDataFormatter\Specs;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

/**
 * EDS RecordDataFormatter specs.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class EDS extends DefaultRecord
{
    /**
     * Initialize specs.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->setDefaults('core', [$this, 'getDefaultCoreSpecs']);
        $this->setDefaults('result-list', [$this, 'getDefaultResultListSpecs']);
        $this->setDefaults('description', [$this, 'getDefaultDescriptionSpecs']);
    }

    /**
     * Get specs for EDS items rendered as multi line.
     *
     * @return array
     */
    protected function getItemsSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec->setMultiLine(
            'Items',
            'getItems',
            $this->getMultiLineItemMapper(),
            [
                'multiRenderType' => 'RecordDriverTemplate',
                'template' => 'data-item.phtml',
            ]
        );
        return $spec->getArray();
    }

    /**
     * Get method that maps items to a format for multi line rendering.
     *
     * @return callable
     */
    protected function getMultiLineItemMapper(): callable
    {
        return function ($data) {
            return array_map(function ($item) {
                $item['label'] = $item['Label'];
                $item['values'] = $item;
                return $item;
            }, $data);
        };
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs(): array
    {
        return $this->getItemsSpecs();
    }

    /**
     * Get default specifications for displaying data in the result list.
     *
     * @return array
     */
    public function getDefaultResultListSpecs(): array
    {
        return $this->getItemsSpecs();
    }
}
