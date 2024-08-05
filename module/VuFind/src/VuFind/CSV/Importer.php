<?php

/**
 * VuFind CSV importer configuration
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\CSV;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Service\GetServiceTrait;
use VuFindSearch\Backend\Solr\Document\RawJSONDocument;

use function count;

/**
 * VuFind CSV importer configuration
 *
 * @category VuFind
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class Importer
{
    use GetServiceTrait;

    /**
     * Base path for loading .ini files
     *
     * @var string
     */
    protected $configBaseDir;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm      Service manager
     * @param array                   $options Configuration options
     */
    public function __construct(ServiceLocatorInterface $sm, array $options = [])
    {
        $this->serviceLocator = $sm;
        $this->configBaseDir = $options['configBaseDir'] ?? 'import';
    }

    /**
     * Save a CSV file to the Solr index using the specified configuration.
     *
     * @param string $csvFile  CSV file to load.
     * @param string $iniFile  INI file.
     * @param string $index    Solr index to use.
     * @param bool   $testMode Are we in test-only mode?
     *
     * @throws \Exception
     * @return string          Output for test mode
     */
    public function save(
        string $csvFile,
        string $iniFile,
        string $index = 'Solr',
        bool $testMode = false
    ): string {
        $in = fopen($csvFile, 'r');
        if (!$in) {
            throw new \Exception("Cannot open CSV file: {$csvFile}.");
        }
        $config = $this->getConfiguration($iniFile, $in);
        $batchSize = $config->getBatchSize();
        $encoding = $config->getEncoding();
        $data = [];
        $output = '';
        while ($line = fgetcsv($in)) {
            $data[] = $this->collectValuesFromLine(
                $this->adjustEncoding($line, $encoding),
                $config
            );
            // If we have finished a batch, write it now and start the next one:
            if (count($data) === $batchSize) {
                $output .= $this->writeData($data, $index, $testMode);
                $data = [];
            }
        }
        fclose($in);
        // If there's an incomplete batch in progress, write the remaining data:
        if (!empty($data)) {
            $output .= $this->writeData($data, $index, $testMode);
        }

        return $output;
    }

    /**
     * Fix the character encoding of a CSV line (if necessary).
     *
     * @param array  $line     Input from CSV
     * @param string $encoding Encoding of $line
     *
     * @return array           Input re-encoded as UTF-8 (if not already in UTF-8)
     */
    protected function adjustEncoding(array $line, string $encoding): array
    {
        // We want UTF-8, so if that's already the setting, we don't need to do work:
        if (strtolower($encoding) === 'utf-8') {
            return $line;
        }
        return array_map(
            function (string $str) use ($encoding): string {
                return iconv($encoding, 'UTF-8', $str);
            },
            $line
        );
    }

    /**
     * Write a batch of JSON data to Solr.
     *
     * @param array  $data     Data to write
     * @param string $index    Target Solr index
     * @param bool   $testMode Are we in test mode?
     *
     * @return string          Test mode output (if applicable) or empty string
     */
    protected function writeData(array $data, string $index, bool $testMode): string
    {
        // Format the data appropriately (human-readable for test-mode, concise
        // for real Solr writing):
        $flags = $testMode ? JSON_PRETTY_PRINT : 0;
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new \Exception(json_last_error_msg(), json_last_error());
        }

        // Save the results (or just return them, if in test mode):
        if ($testMode) {
            return $json;
        }
        $solr = $this->getService(\VuFind\Solr\Writer::class);
        $solr->save($index, new RawJSONDocument($json), 'update');
        return ''; // no output when not in test mode!
    }

    /**
     * Process the header row, and generate a configuration.
     *
     * @param ImporterConfig $config Configuration to be updated
     * @param resource       $in     File handle to CSV
     * @param string         $mode   Header processing mode (fields/none/skip)
     *
     * @return void
     */
    protected function processHeader(ImporterConfig $config, $in, string $mode): void
    {
        switch (strtolower(trim($mode))) {
            case 'fields':
                // Load configuration from the header row:
                $row = fgetcsv($in);
                foreach ($row as $i => $field) {
                    $config->configureColumn($i, ['field' => $field]);
                }
                break;
            case 'skip':
                //  Just skip a row:
                fgetcsv($in);
                break;
            case 'none':
            default:
                // Do nothing.
                break;
        }
    }

    /**
     * Load and set up the configuration object.
     *
     * @param string   $iniFile Name of .ini file to load
     * @param resource $in      File handle to input file
     *
     * @throws \Exception
     * @return ImporterConfig
     */
    protected function getConfiguration(string $iniFile, $in): ImporterConfig
    {
        // Load properties file:
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        $ini = $resolver->getConfigPath($iniFile, $this->configBaseDir);
        if (!file_exists($ini)) {
            throw new \Exception("Cannot load .ini file: {$ini}.");
        }
        $options = parse_ini_file($ini, true);
        return $this->processConfiguration($options, $in);
    }

    /**
     * Determine the list of fields that will be loaded.
     *
     * @param array    $options Configuration
     * @param resource $in      File handle to input file
     *
     * @throws \Exception
     * @return ImporterConfig
     */
    protected function processConfiguration(array $options, $in): ImporterConfig
    {
        $config = new ImporterConfig($options['General'] ?? []);
        $this->processHeader($config, $in, $options['General']['header'] ?? 'none');
        foreach ($options as $section => $settings) {
            if (str_contains($section, ':')) {
                [$type, $details] = explode(':', $section);
                switch (strtolower(trim($type))) {
                    case 'column':
                        $config->configureColumn($details, $settings);
                        break;
                    case 'field':
                        $config->configureField($details, $settings);
                        break;
                    default:
                        throw new \Exception("Unexpected config section: $section");
                }
            }
        }
        return $config;
    }

    /**
     * Inject dependencies into the callback, if necessary.
     *
     * @param string $callable Callback function
     *
     * @return void
     */
    protected function injectCallbackDependencies(string $callable): void
    {
        // Use a static property to keep track of which static classes
        // have already had dependencies injected.
        static $alreadyInjected = [];

        // $callable is one of two formats: "function" or "class::method".
        // We only want to proceed if we have a class name.
        $parts = explode('::', $callable);
        if (count($parts) < 2) {
            return;
        }
        $class = $parts[0];

        // If we haven't already injected dependencies, do it now! This makes
        // it possible to use callbacks from the XSLT importer
        // (e.g. \VuFind\XSLT\Import\VuFind::harvestWithParser)
        if (!isset($alreadyInjected[$class])) {
            if (method_exists($class, 'setServiceLocator')) {
                $class::setServiceLocator($this->serviceLocator);
            }
            $alreadyInjected[$class] = true;
        }
    }

    /**
     * Apply a single callback to a single value.
     *
     * @param string $callback    Callback string from config
     * @param string $value       Value to process
     * @param array  $fieldValues Field values processed so far
     *
     * @return string[]
     */
    protected function processCallback(
        string $callback,
        string $value,
        array $fieldValues
    ): array {
        preg_match('/([^(]+)(\(.*\))?/', $callback, $matches);
        $callable = $matches[1];
        $this->injectCallbackDependencies($callable);
        $arglist = array_map(
            'trim',
            explode(
                ',',
                ltrim(rtrim($matches[2] ?? '$$csv$$', ')'), '(')
            )
        );
        $argCallback = function ($arg) use ($value, $fieldValues) {
            if (
                str_starts_with($arg, '$$')
                && str_ends_with($arg, '$$')
            ) {
                $parts = explode(':', trim($arg, '$'), 2);
                switch ($parts[0]) {
                    case 'csv':
                        return $value;
                    case 'field':
                        return $fieldValues[$parts[1] ?? ''] ?? [];
                    case 'fieldFirst':
                        return $fieldValues[$parts[1] ?? ''][0] ?? '';
                    default:
                        throw new \Exception('Unknown directive: ' . $parts[0]);
                }
            }
            return $arg;
        };
        $result = $callable(...array_map($argCallback, $arglist));
        return (array)$result;
    }

    /**
     * Recursively apply callback functions to a value.
     *
     * @param string   $value       Value to process
     * @param string[] $callbacks   List of callback functions
     * @param array    $fieldValues Field values processed so far
     *
     * @return string[]
     */
    protected function applyCallbacks(
        string $value,
        array $callbacks,
        array $fieldValues
    ): array {
        // No callbacks, no work:
        if (empty($callbacks)) {
            return [$value];
        }

        // Get the next callback, apply it, and then recurse over its
        // return values.
        $nextCallback = array_shift($callbacks);
        $recurseFunction = function (string $val) use (
            $callbacks,
            $fieldValues
        ): array {
            return $this->applyCallbacks($val, $callbacks, $fieldValues);
        };
        $next = $this->processCallback($nextCallback, $value, $fieldValues);
        $result = array_merge(...array_map($recurseFunction, $next));
        return $result;
    }

    /**
     * Process the values from a single column of a CSV.
     *
     * @param string[] $values      Values to process
     * @param array    $fieldConfig Configuration to apply to values
     * @param array    $fieldValues Field values processed so far
     *
     * @return string[]
     */
    protected function processValues(
        array $values,
        array $fieldConfig,
        array $fieldValues
    ): array {
        $processed = [];
        foreach ($values as $value) {
            $newValues = $this->applyCallbacks(
                $value,
                (array)($fieldConfig['callback'] ?? []),
                $fieldValues
            );
            $processed = array_merge($processed, $newValues);
        }
        return $processed;
    }

    /**
     * Collect field-specific values from a CSV input line. Returns an array
     * mapping field name to value array.
     *
     * @param array          $line   Line to process.
     * @param ImporterConfig $config Configuration object.
     *
     * @return array
     */
    protected function collectValuesFromLine(
        array $line,
        ImporterConfig $config
    ): array {
        // First get all hard-coded values...
        $fieldValues = $config->getFixedFieldValues();

        // Now add values mapped directly from the CSV columns...
        $allMappedFields = [];
        foreach ($line as $column => $value) {
            $columnConfig = $config->getColumn($column);
            $values = isset($columnConfig['delimiter'])
                ? explode($columnConfig['delimiter'], $value)
                : (array)$value;
            if (isset($columnConfig['field'])) {
                $fieldList = (array)$columnConfig['field'];
                $allMappedFields = array_merge($allMappedFields, $fieldList);
                foreach ($fieldList as $field) {
                    $fieldConfig = $config->getField($field);
                    $processed = $this->processValues(
                        $values,
                        $fieldConfig,
                        $fieldValues
                    );
                    $fieldValues[$field] = array_merge(
                        $fieldValues[$field],
                        $processed
                    );
                }
            }
        }

        // Finally, add any values derived from other fields...
        $remainingFields = $config->getOutstandingCallbacks($allMappedFields);
        foreach ($remainingFields as $field) {
            $fieldConfig = $config->getField($field);
            $processed = $this->processValues(
                (array)($fieldConfig['callbackSeed'] ?? []),
                $fieldConfig,
                $fieldValues
            );
            $fieldValues[$field] = array_merge($fieldValues[$field], $processed);
        }

        return $fieldValues;
    }
}
