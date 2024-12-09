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

/**
 * VuFind CSV importer configuration
 *
 * @category VuFind
 * @package  CSV
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class ImporterConfig
{
    /**
     * Column data
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Field data
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Batch size
     *
     * @var int
     */
    protected $batchSize = 100;

    /**
     * Input character encoding
     *
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * Constructor
     *
     * @param array $options Config options
     */
    public function __construct($options = [])
    {
        if (isset($options['batchSize']) && $options['batchSize'] > 0) {
            $this->batchSize = $options['batchSize'];
        }
        if (isset($options['encoding'])) {
            $this->encoding = $options['encoding'];
        }
    }

    /**
     * Get batch size setting
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Get encoding setting
     *
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Add column configuration
     *
     * @param int   $column Column number
     * @param array $config Column configuration
     *
     * @return void
     */
    public function configureColumn(int $column, array $config): void
    {
        // Merge the incoming configuration with any existing configuration:
        $this->columns[$column] = array_merge($this->getColumn($column), $config);

        // If the configuration contains field names, initialize those configs
        // so we are sure to have a complete field list from getFields():
        if (isset($config['field'])) {
            foreach ((array)$config['field'] as $field) {
                if (!isset($this->fields[$field])) {
                    $this->fields[$field] = [];
                }
            }
        }
    }

    /**
     * Add field configuration
     *
     * @param string $name   Field name
     * @param array  $config Field configuration
     *
     * @return void
     */
    public function configureField(string $name, array $config): void
    {
        // Merge the incoming configuration with any existing configuration:
        $this->fields[$name] = array_merge($this->getField($name), $config);
    }

    /**
     * Get configuration for the specified column.
     *
     * @param int $column Column number
     *
     * @return array
     */
    public function getColumn(int $column): array
    {
        return $this->columns[$column] ?? [];
    }

    /**
     * Get configuration for the specified field.
     *
     * @param string $name Field name
     *
     * @return array
     */
    public function getField(string $name): array
    {
        return $this->fields[$name] ?? [];
    }

    /**
     * Get all field names
     *
     * @return string[]
     */
    public function getAllFields(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get a list of fields with callbacks that have not already been processed.
     *
     * @param string[] $processed List of fields that have already been processed.
     *
     * @return string[]
     */
    public function getOutstandingCallbacks(array $processed): array
    {
        // Get a list of fields that have not already been processed, and then
        // filter out any that lack a callback configuration.
        return array_filter(
            array_diff($this->getAllFields(), $processed),
            function ($field) {
                return isset($this->getField($field)['callback']);
            }
        );
    }

    /**
     * Initialize a field array with any fixed values configured here.
     *
     * @return array
     */
    public function getFixedFieldValues()
    {
        $fields = [];
        foreach ($this->getAllFields() as $field) {
            $values = $this->getField($field)['value'] ?? [];
            $fields[$field] = (array)$values;
        }
        return $fields;
    }
}
