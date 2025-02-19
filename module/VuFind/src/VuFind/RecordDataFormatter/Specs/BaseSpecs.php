<?php

/**
 * Base of RecordDataFormatter specs.
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

use function is_array;
use function is_callable;

/**
 * Base of RecordDataFormatter specs.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class BaseSpecs implements SpecInterface, \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Default settings.
     *
     * @var array
     */
    protected array $defaults = [];

    /**
     * Constructor
     *
     * @param array $config Config
     */
    public function __construct(protected array $config)
    {
        $this->init();
    }

    /**
     * Initialize specs.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->setDefaults('description', [$this, 'getDefaultDescriptionSpecs']);
    }

    /**
     * Get default configuration.
     *
     * @param string $key Key for configuration to look up.
     *
     * @return array
     */
    public function getDefaults(string $key): array
    {
        // No value stored? Return empty array:
        if (!isset($this->defaults[$key])) {
            return [];
        }
        // Callback stored? Resolve to array on demand:
        if (is_callable($this->defaults[$key])) {
            $this->defaults[$key] = $this->defaults[$key]();
            if (!is_array($this->defaults[$key])) {
                throw new \Exception('Callback for ' . $key . ' must return array');
            }
        }
        // Adding defaults from config
        foreach ($this->config['Defaults'][$key] ?? [] as $field) {
            $this->defaults[$key][$field] = [];
        }
        // Adding options from config
        foreach ($this->defaults[$key] as $field => $options) {
            $this->defaults[$key][$field] = $this->addOptions($key, $field, $options);
        }
        // Send back array:
        return $this->defaults[$key];
    }

    /**
     * Set default configuration.
     *
     * @param string         $key    Key for configuration to set.
     * @param array|callable $values Defaults to store (either an array, or a
     * callable returning an array).
     *
     * @return void
     */
    public function setDefaults(string $key, array|callable $values): void
    {
        if (!is_array($values) && !is_callable($values)) {
            throw new \Exception('$values must be array or callable');
        }
        $this->defaults[$key] = $values;
    }

    /**
     * Add global and configured options to options of a field.
     *
     * @param string $context Context of the field.
     * @param string $field   Field
     * @param array  $options Options of a field.
     *
     * @return ?array
     */
    protected function addOptions(string $context, string $field, array $options): ?array
    {
        if ($globalOptions = ($this->config['Global'] ?? false)) {
            $options = array_filter($options, function ($val) {
                return $val !== null;
            });
            $options = array_merge($globalOptions, $options);
        }

        $section = 'Field_' . $field;
        if ($fieldOptions = ($this->config[$section] ?? false)) {
            $fieldOptions = array_filter($fieldOptions, function ($val) {
                return $val !== null;
            });
            $options = array_merge($options, $fieldOptions);
        }

        $contextSection = $options['overrideContext'][$context] ?? false;
        if (
            $contextOptions = $this->config[$contextSection] ?? false
        ) {
            $contextOptions = array_filter($contextOptions, function ($val) {
                return $val !== null;
            });
            $options = array_merge($options, $contextOptions);
        }

        return $options;
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    protected function getDefaultDescriptionSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec->setTemplateLine('Summary', true, 'data-summary.phtml');
        $spec->setLine('Abstract', 'getAbstractNotes');
        $spec->setLine('Review', 'getReviewNotes');
        $spec->setLine('Content Advice', 'getContentAdviceNotes');
        $spec->setLine('Published', 'getDateSpan');
        $spec->setLine('Item Description', 'getGeneralNotes');
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('Format', 'getSystemDetails');
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        $spec->setLine(
            'ISBN',
            'getISBNs',
            null,
            ['itemPrefix' => '<span property="isbn">', 'itemSuffix' => '</span>']
        );
        $spec->setLine(
            'ISSN',
            'getISSNs',
            null,
            ['itemPrefix' => '<span property="issn">', 'itemSuffix' => '</span>']
        );
        $spec->setLine(
            'DOI',
            'getCleanDOI',
            null,
            [
                'itemPrefix' => '<span property="identifier">',
                'itemSuffix' => '</span>',
            ]
        );
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setLine('Source', 'getSource');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }
}
