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

use Laminas\View\Renderer\PhpRenderer;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

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
     * Property to cache active formats
     * (initialized to empty array , populated later)
     *
     * @var array
     */
    protected $activeFormats = [];

    /**
     * Constructor
     *
     * @param array       $mainConfig   Main VuFind configuration
     * @param array       $exportConfig Export-specific configuration
     * @param PhpRenderer $viewRenderer View renderer
     */
    public function __construct(
        protected array $mainConfig,
        protected array $exportConfig,
        protected PhpRenderer $viewRenderer
    ) {
    }

    /**
     * Get the URL for bulk export.
     *
     * @param string $format Export format being used
     * @param array  $ids    Array of IDs to export (in source|id format)
     *
     * @return string
     */
    public function getBulkUrl(string $format, array $ids): string
    {
        $params = ['f=' . urlencode($format)];
        foreach ($ids as $id) {
            $params[] = urlencode('i[]') . '=' . urlencode($id);
        }
        $serverUrlHelper = $this->viewRenderer->plugin('serverurl');
        $urlHelper = $this->viewRenderer->plugin('url');
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
    public function getRedirectUrl(string $format, string $callback): string
    {
        // Fill in special tokens in template:
        $template = $this->exportConfig[$format]['redirectUrl'] ?? '';
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        foreach ($matches[1] as $current) {
            $parts = explode('|', $current);
            switch ($parts[0]) {
                case 'config':
                case 'encodedConfig':
                    if (null !== ($configValue = $this->mainConfig[$parts[1]][$parts[2]] ?? null)) {
                        $value = $configValue;
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
    public function needsRedirect(string $format): bool
    {
        return !empty($this->exportConfig[$format]['redirectUrl'])
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
    public function processGroup(string $format, array $parts): string
    {
        if (!$parts) {
            return '';
        }

        // If we're in XML mode, we need to do some special processing:
        if ($combineXpath = $this->exportConfig[$format]['combineXpath'] ?? null) {
            $ns = array_map(
                function ($current) {
                    return explode('|', $current, 2);
                },
                $this->exportConfig[$format]['combineNamespaces'] ?? []
            );
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
                    $matches = $current->xpath($combineXpath);
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
     * @param RecordDriver $driver Record driver
     * @param string       $format Format to check
     *
     * @return bool
     */
    public function recordSupportsFormat(RecordDriver $driver, string $format): bool
    {
        // Check if the driver explicitly disallows the format:
        if ($driver->tryMethod('exportDisabled', [$format])) {
            return false;
        }

        // Check if the format is configured:
        if (empty($this->exportConfig[$format])) {
            return false;
        }

        // Check the requirements for export in the requested format:
        foreach ($this->exportConfig[$format]['requiredMethods'] ?? [] as $method) {
            // If a required method is missing, give up now:
            if (!is_callable([$driver, $method])) {
                return false;
            }
        }

        // If we got this far, we didn't encounter a problem, and the
        // requested export format is valid, so we can report success!
        return true;
    }

    /**
     * Get an array of strings representing formats in which a specified record's
     * data may be exported (empty if none). Legal values: "BibTeX", "EndNote",
     * "MARC", "MARCXML", "RDF", "RefWorks".
     *
     * @param RecordDriver $driver Record driver
     *
     * @return array Strings representing export formats.
     */
    public function getFormatsForRecord(RecordDriver $driver): array
    {
        // Get an array of enabled export formats (from config, or use defaults
        // if nothing in config array).
        $active = $this->getActiveFormats('record');

        // Loop through all possible formats:
        $formats = [];
        foreach (array_keys($this->exportConfig) as $format) {
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
    public function getFormatsForRecords(array $drivers): array
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
    public function getHeaders(string $format): array
    {
        return (array)($this->exportConfig[$format]['headers'] ?? []);
    }

    /**
     * Get the display label for the specified export format.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getLabelForFormat(string $format): string
    {
        return $this->exportConfig[$format]['label'] ?? $format;
    }

    /**
     * Get the bulk export type for the specified export format.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getBulkExportType(string $format): string
    {
        // if exportType is set on per-format basis in export.ini then use it
        // else check if export type is set in config.ini
        return $this->exportConfig[$format]['bulkExportType']
            ?? $this->mainConfig['BulkExport']['defaultType'] ?? 'link';
    }

    /**
     * Get active export formats for the given context.
     *
     * @param string $context Export context (i.e. record, bulk)
     *
     * @return array
     */
    public function getActiveFormats(string $context = 'record'): array
    {
        if (!isset($this->activeFormats[$context])) {
            $formatSettings = $this->mainConfig['Export']
                ?? ['RefWorks' => 'record,bulk', 'EndNote' => 'record,bulk'];

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
                && ($this->mainConfig['BulkExport']['enabled'] ?? false)
                && $bulkOptions = $this->mainConfig['BulkExport']['options'] ?? null
            ) {
                $config = explode(':', $bulkOptions);
                foreach ($config as $option) {
                    if ($this->mainConfig['Export'][$option] ?? false) {
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
    public function getPostField(string $format): string
    {
        $postField = $this->exportConfig[$format]['postField'] ?? null;
        return $postField ?: 'ImportData';
    }

    /**
     * Get the export target window.
     *
     * @param string $format Format identifier
     *
     * @return string
     */
    public function getTargetWindow(string $format): string
    {
        $targetWindow = $this->exportConfig[$format]['targetWindow'] ?? null;
        return $targetWindow ?: $format . 'Main';
    }
}
