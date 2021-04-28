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
use VuFindSearch\Backend\Solr\Document\RawCSVDocument;
use VuFindSearch\ParamBag;

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
        [$config, $csv] = $this->generateCSV($csvFile, $iniFile);
        $fields = $config->getAllFields();
        $params = new ParamBag(['fieldnames' => implode(',', $fields)]);
        foreach ($fields as $field) {
            $delimiter = $config->getDelimiter($field);
            if (!empty($delimiter)) {
                $params->set("f.$field.split", 'true');
                $params->set("f.$field.separator", $delimiter);
            }
        }

        // Save the results (or just display them, if in test mode):
        if (!$testMode) {
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);
            $solr->save(
                $index,
                new RawCSVDocument($csv), 'csv', 'update', $params
            );
        }
        return $csv;
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
     * Recursively apply callback functions to a value.
     *
     * @param string   $value     Value to process
     * @param string[] $callbacks List of callback functions
     *
     * @return string|string[]
     */
    protected function applyCallbacks(string $value, array $callbacks)
    {
        // No callbacks, no work:
        if (empty($callbacks)) {
            return $value;
        }

        // Get the next callback, apply it, and then recurse over its
        // return values.
        $nextCallback = array_shift($callbacks);
        $recurseFunction = function ($val) use ($callbacks) {
            return $this->applyCallbacks($val, $callbacks);
        };
        return array_map($recurseFunction, (array)$nextCallback($value));
    }

    /**
     * Process the values from a single column of a CSV.
     *
     * @param string[] $values      Values to process
     * @param array    $fieldConfig Configuration to apply to values
     *
     * @return string[]
     */
    protected function processValues(array $values, array $fieldConfig): array
    {
        $processed = [];
        foreach ($values as $value) {
            $newValues = $this
                ->applyCallbacks($value, $fieldConfig['callback'] ?? []);
            $processed = array_merge($processed, (array)$newValues);
        }
        return $processed;
    }

    /**
     * Process a single line of the CSV file.
     *
     * @param array          $line   Line to process.
     * @param ImporterConfig $config Configuration object.
     *
     * @return array
     */
    protected function processLine(array $line, ImporterConfig $config): array
    {
        $fieldValues = [];
        foreach ($line as $column => $value) {
            $columnConfig = $config->getColumn($column);
            $values = isset($columnConfig['delimiter'])
                ? explode($columnConfig['delimiter'], $value)
                : (array)$value;
            if (isset($columnConfig['field'])) {
                $field = $columnConfig['field'];
                $fieldConfig = $config->getField($field);
                $fieldValues[$field] = $this->processValues($values, $fieldConfig);
            }
        }
        $output = [];
        foreach ($config->getAllFields() as $field) {
            $delimiter = $config->getDelimiter($field);
            if (empty($delimiter) && count($fieldValues[$field]) > 1) {
                throw new \Exception('Unexpected multiple values in ' . $field);
            }
            $output[] = implode($delimiter, $fieldValues[$field] ?? '');
        }
        return $output;
    }

    /**
     * Transform $csvFile using the provided $iniFile configuration. Returns an
     * array containing two elements: the parsed config object and the processed
     * data.
     *
     * @param string $csvFile CSV file to load.
     * @param string $iniFile INI file.
     *
     * @throws \Exception
     * @return array
     */
    protected function generateCSV(string $csvFile, string $iniFile): array
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
        $out = fopen('php://temp', 'r+');
        while ($line = fgetcsv($in)) {
            fputcsv($out, $this->processLine($line, $config));
        }
        fclose($in);
        rewind($out);
        $csv = '';
        while ($data = fread($out, 1048576)) {
            $csv .= $data;
        }
        fclose($out);

        return [$config, $csv];
    }
}
