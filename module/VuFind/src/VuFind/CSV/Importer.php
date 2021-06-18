<?php
/**
 * VuFind CSV importer configuration
 *
 * PHP version 7
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
use VuFind\Config\Locator as ConfigLocator;
use VuFindSearch\Backend\Solr\Document\RawJSONDocument;

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
    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected ServiceLocatorInterface $serviceLocator;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->serviceLocator = $sm;
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
     * @return string            Transformed XML
     */
    public function save(string $csvFile, string $iniFile,
        string $index = 'Solr', bool $testMode = false
    ): string {
        // Process the file:
        $flags = $testMode ? JSON_PRETTY_PRINT : 0; // only pretty-print for testing
        $json = json_encode($this->processCSV($csvFile, $iniFile), $flags);

        // Save the results (or just display them, if in test mode):
        if (!$testMode) {
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);
            $solr->save($index, new RawJSONDocument($json), 'update');
        }
        return $json;
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
            if (strpos($section, ':') !== false) {
                [$type, $details] = explode(':', $section);
                switch (strtolower(trim($type))) {
                case 'column':
                    $config->configureColumn($details, $settings);
                    break;
                case 'field':
                    $config->configureField($details, $settings);
                    break;
                default:
                    throw new \Exception('Unexpected config section: ' . $section);
                }
            }
        }
        return $config;
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
    protected function processCallback(string $callback, string $value,
        array $fieldValues
    ): array {
        preg_match('/([^(]+)(\(.*\))?/', $callback, $matches);
        $callable = $matches[1];
        $arglist = array_map(
            'trim',
            explode(
                ',',
                ltrim(rtrim($matches[2] ?? '$$csv$$', ')'), '(')
            )
        );
        $argCallback = function ($arg) use ($value, $fieldValues) {
            if (substr($arg, 0, 2) == '$$'
                && substr($arg, -2) == '$$'
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
    protected function applyCallbacks(string $value, array $callbacks,
        array $fieldValues
    ): array {
        // No callbacks, no work:
        if (empty($callbacks)) {
            return [$value];
        }

        // Get the next callback, apply it, and then recurse over its
        // return values.
        $nextCallback = array_shift($callbacks);
        $recurseFunction = function (string $val) use ($callbacks, $fieldValues
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
    protected function processValues(array $values, array $fieldConfig,
        array $fieldValues
    ): array {
        $processed = [];
        foreach ($values as $value) {
            $newValues = $this->applyCallbacks(
                $value, (array)($fieldConfig['callback'] ?? []), $fieldValues
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
    protected function collectValuesFromLine(array $line, ImporterConfig $config
    ): array {
        $fieldValues = $config->getFixedFieldValues();
        foreach ($line as $column => $value) {
            $columnConfig = $config->getColumn($column);
            $values = isset($columnConfig['delimiter'])
                ? explode($columnConfig['delimiter'], $value)
                : (array)$value;
            if (isset($columnConfig['field'])) {
                $fieldList = (array)$columnConfig['field'];
                foreach ($fieldList as $field) {
                    $fieldConfig = $config->getField($field);
                    $processed = $this->processValues(
                        $values, $fieldConfig, $fieldValues
                    );
                    $fieldValues[$field] = array_merge(
                        $fieldValues[$field], $processed
                    );
                }
            }
        }
        return $fieldValues;
    }

    /**
     * Transform $csvFile using the provided $iniFile configuration. Returns an
     * array suitable for JSON encoding representing the processed data.
     *
     * @param string $csvFile CSV file to load.
     * @param string $iniFile INI file.
     *
     * @throws \Exception
     * @return array
     */
    protected function processCSV(string $csvFile, string $iniFile): array
    {
        // Load properties file:
        $ini = ConfigLocator::getConfigPath($iniFile, 'import');
        if (!file_exists($ini)) {
            throw new \Exception("Cannot load .ini file: {$ini}.");
        }
        $options = parse_ini_file($ini, true);

        $in = fopen($csvFile, 'r');
        if (!$in) {
            throw new \Exception("Cannot open CSV file: {$csvFile}.");
        }
        $config = $this->processConfiguration($options, $in);
        $json = [];
        while ($line = fgetcsv($in)) {
            $json[] = $this->collectValuesFromLine($line, $config);
        }
        fclose($in);

        return $json;
    }
}
