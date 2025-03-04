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
use VuFind\View\Helper\Root\SchemaOrg;

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
     * Constructor
     *
     * @param array      $config          EDS RecordDataFormatter Config
     * @param ?SchemaOrg $schemaOrgHelper schema.org helper
     * @param array      $edsConfig       EDS Config
     */
    public function __construct(array $config, protected ?SchemaOrg $schemaOrgHelper, protected array $edsConfig)
    {
        parent::__construct($config, $schemaOrgHelper);
    }

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
     * Get default specifications for displaying data in core metadata.
     *
     * @param string $format If number of authors should be limited (Short or Long)
     *
     * @return array
     */
    protected function getAuthorOptions(string $format): array
    {
        $options = [
            'useSearchLink' => 'author',
            'itemPrefix' => '<span class="author">',
            'itemSuffix' => '</span>',
            'separator' => '',
        ];
        if (strtolower($format) == 'short') {
            $options['limit'] = $this->edsConfig['AuthorDisplay']['ShortAuthorLimit'] ?? 3;
            $options['abbreviation'] = ' ' . $this->translate('more_authors_abbrev');
        }
        return $options;
    }

    /**
     * Get filters from section of config.
     *
     * @param string $section Section
     *
     * @return array
     */
    protected function getFilterFromConfigSection(string $section): array
    {
        $filterConfig = $this->edsConfig[$section] ?? [];
        return [
            'Group' => [
                'exclude' => $filterConfig['excludeGroup'] ?? [],
                'include' => $filterConfig['includeGroup'] ?? [],
            ],
            'Label' => [
                'exclude' => $filterConfig['excludeLabel'] ?? [],
                'include' => $filterConfig['includeLabel'] ?? [],
            ],
        ];
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs(): array
    {
        $spec = new SpecBuilder();
        $filter = $this->getFilterFromConfigSection('ItemCoreFilter');
        $format = $this->edsConfig['AuthorDisplay']['DetailPageFormat'] ?? 'Long';
        if (strtolower($format) === 'short') {
            $filter['Group']['exclude'] = ['AuInfo'];
        }
        $spec->addItems($filter, ['separator' => '<br>']);
        $spec->setItemLine('Group', 'Au', $this->getAuthorOptions($format));
        $spec->setItemLine('Group', 'Su', ['useSearchLink' => true]);
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the result list.
     *
     * @return array
     */
    public function getDefaultResultListSpecs(): array
    {
        $spec = new SpecBuilder();
        $filter = $this->getFilterFromConfigSection('ItemResultListFilter');
        $format = $this->edsConfig['AuthorDisplay']['ResultListFormat'] ?? 'Long';
        $spec->addItems($filter);
        $spec->setItemLine('Group', 'Au', $this->getAuthorOptions($format));
        $spec->setItemLine('Group', 'Su', ['useSearchLink' => true]);
        return $spec->getArray();
    }
}
