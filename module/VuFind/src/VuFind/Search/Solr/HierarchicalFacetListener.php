<?php

/**
 * Solr hierarchical facet listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\I18n\TranslatableString;
use VuFind\Service\GetServiceTrait;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

use function in_array;
use function is_array;

/**
 * Solr hierarchical facet handling listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HierarchicalFacetListener
{
    use GetServiceTrait;

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Facet configuration.
     *
     * @var Config
     */
    protected $facetConfig;

    /**
     * Facet helper.
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Facet display styles.
     *
     * @var array
     */
    protected $displayStyles;

    /**
     * Hierarchy level separators
     *
     * @var array
     */
    protected $separators;

    /**
     * Facet settings
     *
     * @var array
     */
    protected $translatedFacets = [];

    /**
     * Text domains for translated facets
     *
     * @var array
     */
    protected $translatedFacetsTextDomains = [];

    /**
     * Constructor.
     *
     * @param BackendInterface        $backend        Search backend
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $facetConfig    Facet config file id
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        ServiceLocatorInterface $serviceLocator,
        $facetConfig
    ) {
        $this->backend = $backend;
        $this->serviceLocator = $serviceLocator;

        $config = $this->getService(\VuFind\Config\PluginManager::class);
        $this->facetConfig = $config->get($facetConfig);
        $this->facetHelper = $this->getService(\VuFind\Search\Solr\HierarchicalFacetHelper::class);

        $specialFacets = $this->facetConfig->SpecialFacets;
        $this->displayStyles
            = isset($specialFacets->hierarchicalFacetDisplayStyles)
            ? $specialFacets->hierarchicalFacetDisplayStyles->toArray()
            : [];
        $this->separators
            = isset($specialFacets->hierarchicalFacetSeparators)
            ? $specialFacets->hierarchicalFacetSeparators->toArray()
            : [];

        $translatedFacets = $this->facetConfig->Advanced_Settings->translated_facets
            ?? [];
        foreach ($translatedFacets as $current) {
            $parts = explode(':', $current);
            $this->translatedFacets[] = $parts[0];
            if (isset($parts[1])) {
                $this->translatedFacetsTextDomains[$parts[0]] = $parts[1];
            }
        }
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach(
            Service::class,
            Service::EVENT_POST,
            [$this, 'onSearchPost']
        );
    }

    /**
     * Format hierarchical facets accordingly
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $command = $event->getParam('command');

        if ($command->getTargetIdentifier() !== $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $command->getContext();
        if (
            $context == 'search' || $context == 'retrieve'
            || $context == 'retrieveBatch' || $context == 'similar'
        ) {
            $this->processHierarchicalFacets($event);
        }
        return $event;
    }

    /**
     * Process hierarchical facets and format them accordingly
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function processHierarchicalFacets($event)
    {
        if (empty($this->facetConfig->SpecialFacets->hierarchical)) {
            return;
        }
        $result = $event->getParam('command')->getResult();
        foreach ($result->getRecords() as $record) {
            $fields = $record->getRawData();
            foreach ($this->facetConfig->SpecialFacets->hierarchical as $facetName) {
                if (!isset($fields[$facetName])) {
                    continue;
                }
                if (is_array($fields[$facetName])) {
                    $allLevels = ($this->displayStyles[$facetName] ?? '') === 'full';
                    foreach ($fields[$facetName] as &$value) {
                        // Include a translation for each value only if we don't
                        // display full hierarchy or this is the deepest hierarchy
                        // level available
                        if (
                            !$allLevels
                            || $this->facetHelper->isDeepestFacetLevel(
                                $fields[$facetName],
                                $value
                            )
                        ) {
                            $value = $this->formatFacetField($facetName, $value);
                        } else {
                            $value
                                = new TranslatableString((string)$value, '', false);
                        }
                    }
                    // Unset the reference:
                    unset($value);
                    $fields[$facetName] = array_unique($fields[$facetName]);
                } else {
                    $fields[$facetName]
                        = $this->formatFacetField($facetName, $fields[$facetName]);
                }
            }

            $record->setRawData($fields);
        }
    }

    /**
     * Format a facet field according to the settings
     *
     * @param string $facet Facet field
     * @param string $value Facet value
     *
     * @return string Formatted field
     */
    protected function formatFacetField($facet, $value)
    {
        $allLevels = isset($this->displayStyles[$facet])
            ? $this->displayStyles[$facet] == 'full'
            : false;
        $separator = $this->separators[$facet] ?? '/';
        $domain = in_array($facet, $this->translatedFacets)
            ? ($this->translatedFacetsTextDomains[$facet] ?? 'default')
            : false;
        $value = $this->facetHelper
            ->formatDisplayText($value, $allLevels, $separator, $domain);

        return $value;
    }
}
