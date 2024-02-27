<?php

/**
 * Export support class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Export
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind;

use Laminas\Config\Config;
use Laminas\View\Renderer\PhpRenderer;

use function in_array;
use function is_callable;

/**
 * Export support class
 *
 * @category VuFind
 * @package  Export
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Export
{
    /**
     * Main VuFind configuration
     *
     * @var Config
     */
    protected $mainConfig;

    /**
     * Export-specific configuration
     *
     * @var Config
     */
    protected $exportConfig;

    /**
     * Property to cache active formats
     * (initialized to empty array , populated later)
     *
     * @var array
     */
    protected $activeFormats = [];

    /**
     * Constructor
     *
     * @param Config $mainConfig   Main VuFind configuration
     * @param Config $exportConfig Export-specific configuration
     */
    public function __construct(Config $mainConfig, Config $exportConfig)
    {
        $this->mainConfig = $mainConfig;
        $this->exportConfig = $exportConfig;
    }

    /**
     * Get the URL for bulk export.
     *
     * @param PhpRenderer $view   View object (needed for URL generation)
     * @param string      $format Export format being used
     * @param array       $ids    Array of IDs to export (in source|id format)
     *
     * @return string
     */
    public function getBulkUrl($view, $format, $ids)
    {
        $params = ['f=' . urlencode($format)];
        foreach ($ids as $id) {
            $params[] = urlencode('i[]') . '=' . urlencode($id);
        }
        $serverUrlHelper = $view->plugin('serverurl');
        $urlHelper = $view->plugin('url');
        $url = $serverUrlHelper($urlHelper('cart-doexport'))
            . '?' . implode('&', $params);

        return $this->needsRedirect($format)
            ? $this->getRedirectUrl($format, $url) : $url;
    }

    /**
     * Build callback URL for export.
     *
     * @param string $format   Export format being used
     * @param string $callback Callback URL for retrieving record(s)
     *
     * @return string
     */
    public function getRedirectUrl($format, $callback)
    {
        // Fill in special tokens in template:
        $template = $this->exportConfig->$format->redirectUrl;
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        foreach ($matches[1] as $current) {
            $parts = explode('|', $current);
            switch ($parts[0]) {
                case 'config':
                case 'encodedConfig':
                    if (isset($this->mainConfig->{$parts[1]}->{$parts[2]})) {
                        $value = $this->mainConfig->{$parts[1]}->{$parts[2]};
                    } else {
                        $value = $parts[3];
                    }
                    if ($parts[0] == 'encodedConfig') {
                        $value = urlencode($value);
                    }
                    $template = str_replace('{' . $current . '}', $value, $template);
                    break;
                case 'encodedCallback':
                    $template = str_replace(
                        '{' . $current . '}',
                        urlencode($callback),
                        $template
                    );
                    break;
            }
        }
        return $template;
    }

    /**
     * Does the requested format require a redirect?
     *
     * @param string $format Format to check
     *
     * @return bool
     */
    public function needsRedirect($format)
    {
        return !empty($this->exportConfig->$format->redirectUrl)
            && 'link' === $this->getBulkExportType($format);
    }

    /**
     * Convert an array of individual records into a single string for display.
     *
     * @param string $format Format of records to process
     * @param array  $parts  Multiple records to process
     *
     * @return string
     */
    public function processGroup($format, $parts)
    {
        // If we're in XML mode, we need to do some special processing:
        if (isset($this->exportConfig->$format->combineXpath)) {
            $ns = isset($this->exportConfig->$format->combineNamespaces)
                ? $this->exportConfig->$format->combineNamespaces->toArray()
                : [];
            $ns = array_map(
                function ($current) {
                    return explode('|', $current, 2);
                },
                $ns
            );
            if (empty($parts)) {
                return '';
            }
            foreach ($parts as $part) {
                // Convert text into XML object:
                $current = simplexml_load_string($part);

                // The first record gets kept as-is; subsequent records get merged
                // in based on the configured XPath (currently only one level is
                // supported)...
                if (!isset($retVal)) {
                    $retVal = $current;
                } else {
                    foreach ($ns as $n) {
                        $current->registerXPathNamespace($n[0], $n[1]);
                    }
                    $matches = $current->xpath(
                        $this->exportConfig->$format->combineXpath
                    );
                    foreach ($matches as $match) {
                        SimpleXML::appendElement($retVal, $match);
                    }
                }
            }
            return $retVal->asXML();
        } else {
            // Not in XML mode -- just concatenate everything together:
            return implode('', $parts);
        }
    }

    /**
     * Does the specified record support the specified export format?
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     * @param string                            $format Format to check
     *
     * @return bool
     */
    public function recordSupportsFormat($driver, $format)
    {
        // Check if the driver explicitly disallows the format:
        if ($driver->tryMethod('exportDisabled', [$format])) {
            return false;
        }

        // Check the requirements for export in the requested format:
        if (isset($this->exportConfig->$format)) {
            if (isset($this->exportConfig->$format->requiredMethods)) {
                foreach ($this->exportConfig->$format->requiredMethods as $method) {
                    // If a required method is missing, give up now:
                    if (!is_callable([$driver, $method])) {
                        return false;
                    }
                }
            }
            // If we got this far, we didn't encounter a problem, and the
            // requested export format is valid, so we can report success!
            return true;
        }

        // If we got this far, we couldn't find evidence of support:
        return false;
    }

    /**
     * Get an array of strings representing formats in which a specified record's
     * data may be exported (empty if none). Legal values: "BibTeX", "EndNote",
     * "MARC", "MARCXML", "RDF", "RefWorks".
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array Strings representing export formats.
     */
    public function getFormatsForRecord($driver)
    {
        // Get an array of enabled export formats (from config, or use defaults
        // if nothing in config array).
        $active = $this->getActiveFormats('record');

        // Loop through all possible formats:
        $formats = [];
        foreach (array_keys($this->exportConfig->toArray()) as $format) {
            if (
                in_array($format, $active)
                && $this->recordSupportsFormat($driver, $format)
            ) {
                $formats[] = $format;
            }
        }

        // Send back the results:
        return $formats;
    }

    /**
     * Same return value as getFormatsForRecord(), but filtered to reflect bulk
     * export configuration and to list only values supported by a set of records.
     *
     * @param array $drivers Array of record drivers
     *
     * @return array
     */
    public function getFormatsForRecords($drivers)
    {
        $formats = $this->getActiveFormats('bulk');
        foreach ($drivers as $driver) {
            // Filter out unsupported export formats:
            $newFormats = [];
            foreach ($formats as $current) {
                if ($this->recordSupportsFormat($driver, $current)) {
                    $newFormats[] = $current;
                }
            }
            $formats = $newFormats;
        }
        return $formats;
    }

    /**
     * Get headers for the requested format.
     *
     * @param string $format Selected export format
     *
     * @return array
     */
    public function getHeaders($format)
    {
        return $this->exportConfig->$format->headers ?? [];
    }

    /**
     * Get the display label for the specified export format.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getLabelForFormat($format)
    {
        return $this->exportConfig->$format->label ?? $format;
    }

    /**
     * Get the bulk export type for the specified export format.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getBulkExportType($format)
    {
        // if exportType is set on per-format basis in export.ini then use it
        // else check if export type is set in config.ini
        return $this->exportConfig->$format->bulkExportType
            ?? $this->mainConfig->BulkExport->defaultType ?? 'link';
    }

    /**
     * Get active export formats for the given context.
     *
     * @param string $context Export context (i.e. record, bulk)
     *
     * @return array
     */
    public function getActiveFormats($context = 'record')
    {
        if (!isset($this->activeFormats[$context])) {
            $formatSettings = isset($this->mainConfig->Export)
                ? $this->mainConfig->Export->toArray()
                : ['RefWorks' => 'record,bulk', 'EndNote' => 'record,bulk'];

            $active = [];
            foreach ($formatSettings as $format => $allowedContexts) {
                if (
                    str_contains($allowedContexts, $context)
                    || ($context == 'record' && $allowedContexts == 1)
                ) {
                    $active[] = $format;
                }
            }

            // for legacy settings [BulkExport]
            if (
                $context == 'bulk'
                && isset($this->mainConfig->BulkExport->enabled)
                && $this->mainConfig->BulkExport->enabled
                && isset($this->mainConfig->BulkExport->options)
            ) {
                $config = explode(':', $this->mainConfig->BulkExport->options);
                foreach ($config as $option) {
                    if (
                        isset($this->mainConfig->Export->$option)
                        && $this->mainConfig->Export->$option == true
                    ) {
                        $active[] = $option;
                    }
                }
            }
            $this->activeFormats[$context] = array_unique($active);
        }
        return $this->activeFormats[$context];
    }

    /**
     * Get the export POST field name.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getPostField($format)
    {
        return !empty($this->exportConfig->$format->postField)
            ? $this->exportConfig->$format->postField : 'ImportData';
    }

    /**
     * Get the export target window.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getTargetWindow($format)
    {
        return !empty($this->exportConfig->$format->targetWindow)
            ? $this->exportConfig->$format->targetWindow
            : $format . 'Main';
    }
}
