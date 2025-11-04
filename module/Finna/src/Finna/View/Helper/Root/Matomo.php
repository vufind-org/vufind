<?php

/**
 * Matomo web analytics view helper for Matomo versions >= 4
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2023.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\View\Helper\Root;

use VuFind\RecordDriver\AbstractBase as RecordDriverBase;

use function in_array;
use function is_array;

/**
 * Matomo web analytics view helper for Matomo versions >= 4
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Matomo extends \VuFind\View\Helper\Root\Matomo
{
    /**
     * Locale settings
     *
     * @var \VuFind\I18n\Locale\LocaleSettings
     */
    protected $localeSettings;

    /**
     * Custom data added using the addCustomData method.
     *
     * @var array
     */
    protected $additionalCustomData = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config                $config  VuFind configuration
     * @param \Laminas\Router\Http\TreeRouteStack  $router  Router
     * @param \Laminas\Http\PhpEnvironment\Request $request Request
     * @param \VuFind\I18n\Locale\LocaleSettings   $locale  Locale settings
     */
    public function __construct(
        \VuFind\Config\Config $config,
        \Laminas\Router\Http\TreeRouteStack $router,
        \Laminas\Http\PhpEnvironment\Request $request,
        \VuFind\I18n\Locale\LocaleSettings $locale
    ) {
        parent::__construct($config, $router, $request);

        $this->localeSettings = $locale;
    }

    /**
     * Add custom data to be tracked.
     *
     * @param string $name  Name
     * @param string $value Value
     *
     * @return void
     */
    public function addCustomData($name, $value)
    {
        $this->additionalCustomData[$name] = $value;
    }

    /**
     * Get the URL for the current page
     *
     * @return string
     */
    protected function getPageUrl(): string
    {
        // Prettify image popup page URL (AJAX/JSON?method=... > /record/[id]/image
        if (
            $this->calledFromImagePopup()
            && !empty($this->params['recordUrl'])
        ) {
            return $this->params['recordUrl'] . '/image';
        }

        return parent::getPageUrl();
    }

    /**
     * Convert a custom data array to JavaScript dimensions code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getCustomDimensionsCode(array $customData): string
    {
        if ($this->calledFromImagePopup()) {
            $customData['Context'] = 'image';
        }

        return parent::getCustomDimensionsCode(
            $this->augmentCustomData($customData)
        );
    }

    /**
     * Convert a custom data array to JavaScript code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getCustomVarsCode(array $customData): string
    {
        $customData = $this->augmentCustomData($customData);

        // Convert custom data to function-specific custom variables
        if ($this->calledFromImagePopup()) {
            // Prepend variable names with 'ImagePopup' unless listed here:
            $preserveName = ['Context', 'RecordAvailableOnline'];

            $result = [];
            foreach ($customData as $key => $val) {
                if (!in_array($key, $preserveName)) {
                    $key = "ImagePopup{$key}";
                }
                $result[$key] = $val;
            }
            $customData = $result;
        }
        if ('PCI' === ($customData['RecordIndex'] ?? '')) {
            foreach (['RecordFormat', 'RecordData', 'RecordSource'] as $var) {
                if (isset($customData[$var])) {
                    $customData["PCI$var"] = $customData[$var];
                    unset($customData[$var]);
                }
            }
        }
        if (isset($customData['RecordAvailableOnline'])) {
            $key = 'yes' === $customData['RecordAvailableOnline']
                ? 'Online' : 'Offline';
            $customData["RecordData$key"] = $customData['RecordData'] ?? '';
        }

        return parent::getCustomVarsCode($customData);
    }

    /**
     * Augment custom data with additional information
     *
     * @param array $customData Custom data
     *
     * @return array
     */
    protected function augmentCustomData(array $customData): array
    {
        if (!empty($this->additionalCustomData)) {
            $customData = array_merge($customData, $this->additionalCustomData);
        }
        if (!empty($this->params['customData'])) {
            $customData = array_merge($customData, $this->params['customData']);
        }
        if ($this->calledFromImagePopup()) {
            $customData['Context'] = 'image';
        }
        return $customData;
    }

    /**
     * Get custom data for record page
     *
     * @param RecordDriverBase $recordDriver Record driver
     *
     * @return array Associative array of custom data
     */
    protected function getRecordPageCustomData(RecordDriverBase $recordDriver): array
    {
        $result = parent::getRecordPageCustomData($recordDriver);

        $sourceMap = ['Solr' => 'Local', 'Primo' => 'PCI'];
        $source = $recordDriver->getSourceIdentifier();
        $result['RecordIndex'] = $sourceMap[$source] ?? $source;

        $result['Language'] = $this->localeSettings->getUserLocale();

        if ($source === 'Primo') {
            $result['RecordSource'] = $recordDriver->getSource();
            unset($result['RecordInstitution']);

            if ($type = $recordDriver->getType()) {
                $result['RecordFormat'] = $type;
            }
        } else {
            $format = $formats = $recordDriver->tryMethod('getFormats');
            if (is_array($formats)) {
                $format = end($formats);
                if (false === $format) {
                    $format = '';
                }
            }
            $format = rtrim($format, '/');
            $format = preg_replace('/^\d\//', '', $format);
            $result['RecordFormat'] = $format;

            $fields = $recordDriver->getRawData();
            $online = !empty($fields['online_boolean']);
            $result['RecordAvailableOnline'] = $online ? 'yes' : 'no';
        }

        return $result;
    }

    /**
     * Get custom data for lightbox actions
     *
     * @return array Associative array of custom data
     */
    protected function getLightboxCustomData(): array
    {
        if ($this->calledFromImagePopup()) {
            return $this->getRecordPageCustomData($this->params['record']);
        }
        return parent::getLightboxCustomData();
    }

    /**
     * Check if the view helper was called from image popup template.
     *
     * @return bool
     */
    protected function calledFromImagePopup(): bool
    {
        return isset($this->params['action'])
            && $this->params['action'] == 'imagePopup'
            && isset($this->params['record']);
    }

    /**
     * Get Page View Tracking Code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackPageViewCode(array $customData): string
    {
        $result = parent::getTrackPageViewCode($customData);
        $result .= <<<EOT
            _paq.push(['trackVisibleContentImpressions']);

            EOT;
        return $result;
    }
}
