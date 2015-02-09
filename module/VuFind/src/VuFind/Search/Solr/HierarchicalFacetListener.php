<?php

/**
 * Solr hierarchical facet listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\I18n\Translator\TranslatorInterface;

/**
 * Solr hierarchical facet handling listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class HierarchicalFacetListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

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
     * Translator.
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Translated facets.
     *
     * @var array
     */
    protected $translatedFacets;

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

        $config = $this->serviceLocator->get('VuFind\Config');
        $this->facetConfig = $config->get($facetConfig);
        $this->facetHelper
            = $this->serviceLocator->get('VuFind\HierarchicalFacetHelper');

        $this->translator = $this->serviceLocator->get('VuFind\Translator');
        $this->translatedFacets
            = isset($this->facetConfig->Advanced_Settings->translated_facets)
            ? $this->facetConfig->Advanced_Settings->translated_facets->toArray()
            : array();
        $specialFacets = $this->facetConfig->SpecialFacets;
        $this->displayStyles
            = isset($specialFacets->hierarchicalFacetDisplayStyles)
            ? $specialFacets->hierarchicalFacetDisplayStyles->toArray()
            : array();
        $this->separators
            = isset($specialFacets->hierarchicalFacetSeparators)
            ? $specialFacets->hierarchicalFacetSeparators->toArray()
            : array();
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
        $manager->attach('VuFind\Search', 'post', array($this, 'onSearchPost'));
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
        $backend = $event->getParam('backend');

        if ($backend != $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $event->getParam('context');
        if ($context == 'search' || $context == 'retrieve') {
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
        $result = $event->getTarget();
        foreach ($result->getRecords() as $record) {
            $fields = $record->getRawData();
            foreach ($this->facetConfig->SpecialFacets->hierarchical as $facetName) {
                if (!isset($fields[$facetName])) {
                    continue;
                }
                if (is_array($fields[$facetName])) {
                    // If full facet display style is used, discard all but the
                    // most significant value
                    if (isset($this->displayStyles[$facetName])
                        && $this->displayStyles[$facetName] == 'full'
                    ) {
                        $fields[$facetName] = array(
                            $this->formatFacetField(
                                $facetName, end($fields[$facetName])
                            )
                        );
                    } else {
                        foreach ($fields[$facetName] as &$value) {
                            $value = $this->formatFacetField($facetName, $value);
                        }
                        $fields[$facetName] = array_unique($fields[$facetName]);
                    }
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
        if (in_array($facet, $this->translatedFacets)) {
            $value = $this->translator->translate($value);
        }

        $allLevels = isset($this->displayStyles[$facet])
            ? $this->displayStyles[$facet] == 'full'
            : false;
        $separator = isset($this->separators[$facet])
            ? $this->separators[$facet]
            : '/';
        $value = $this->facetHelper->formatDisplayText(
            $value, $allLevels, $separator
        );

        return $value;
    }
}
