<?php

/**
 * VuFind Action Helper - Database upgrade tools
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use Laminas\Db\Adapter\Adapter as DbAdapter;
use Laminas\Db\Metadata\Source\Factory as DbMetadataSourceFactory;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

use function count;
use function in_array;
use function is_object;

/**
 * Action helper to perform database upgrades
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DbUpgrade extends AbstractPlugin
{
    /**
     * Database commands to generate table
     *
     * @var array
     */
    protected $dbCommands = [];

    /**
     * Database adapter
     *
     * @var DbAdapter
     */
    protected $adapter;

    /**
     * Table metadata
     *
     * @var array
     */
    protected $tableInfo = false;

    /**
     * Deprecated columns, keyed by table name
     *
     * @var array
     */
    protected $deprecatedColumns = [
        'search' => ['folder_id'],
    ];

    /**
     * Given a SQL file, parse it for table creation commands.
     *
     * @param string $file Filename to load.
     *
     * @return void
     */
    public function loadSql($file)
    {
        $sql = file_get_contents($file);
        $statements = explode(';', $sql);

        // Create an array, indexed by table name, of commands necessary to create
        // the keyed table:
        foreach ($statements as $statement) {
            preg_match(
                '/(create\s+table|alter\s+table)\s+([^\s(]+).*/mi',
                $statement,
                $matches
            );
            if (isset($matches[2])) {
                $table = str_replace('`', '', $matches[2]);
                if (!isset($this->dbCommands[$table])) {
                    $this->dbCommands[$table] = [];
                }
                $this->dbCommands[$table][] = $statement;
            }
        }
    }

    /**
     * Get the database adapter.
     *
     * @return DbAdapter
     */
    public function getAdapter()
    {
        if (!is_object($this->adapter)) {
            throw new \Exception('Database adapter not set.');
        }
        return $this->adapter;
    }

    /**
     * Set a database adapter.
     *
     * @param DbAdapter $adapter Adapter to set
     *
     * @return DbUpgrade
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Execute a query.
     *
     * @param string $sql    SQL to run
     * @param bool   $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @return string        SQL if $logsql is true, empty string otherwise
     */
    public function query($sql, $logsql)
    {
        if ($logsql) {
            return rtrim($sql, ';') . ";\n";
        } else {
            $this->getAdapter()->query($sql, DbAdapter::QUERY_MODE_EXECUTE);
        }
        return '';
    }

    /**
     * Load table metadata.
     *
     * @param bool $reload Force a reload? (Default is false).
     *
     * @return array
     */
    protected function getTableInfo($reload = false)
    {
        if ($reload || !$this->tableInfo) {
            $metadata = DbMetadataSourceFactory::createSourceFromAdapter(
                $this->getAdapter()
            );
            $tables = $metadata->getTables();
            $this->tableInfo = [];
            foreach ($tables as $current) {
                $this->tableInfo[$current->getName()] = $current;
            }
        }
        return $this->tableInfo;
    }

    /**
     * Get a list of all tables in the database.
     *
     * @throws \Exception
     * @return array
     */
    protected function getAllTables()
    {
        return array_keys($this->getTableInfo());
    }

    /**
     * Support method for getCharsetAndCollationProblemsForTable() -- get column
     * details
     *
     * @param string $table     Table to check
     * @param string $collation The desired collation
     *
     * @throws \Exception
     * @return array
     */
    protected function getCharsetAndCollationProblemsForTableColumns(
        $table,
        $collation
    ) {
        $collation = strtolower($collation);

        // Get column summary:
        $sql = "SHOW FULL COLUMNS FROM `{$table}`";
        $results = $this->getAdapter()->query($sql, DbAdapter::QUERY_MODE_EXECUTE);

        // Get expected column types:
        // Parse column names out of the CREATE TABLE SQL, which will always be
        // the first entry in the array; we assume the standard mysqldump
        // formatting is used here.
        preg_match_all(
            '/^  `([^`]*)`\s+([^\s,]+)[\t ,]+.*$/m',
            $this->dbCommands[$table][0],
            $matches
        );
        $expectedTypes = array_combine($matches[1], $matches[2]);

        // Load details:
        $retVal = [];
        foreach ($results as $current) {
            // json fields default to utf8mb4_bin, and we only support that:
            if (($expectedTypes[$current->Field] ?? '') === 'json') {
                continue;
            }
            if (!empty($current->Collation)) {
                $normalizedCollation = strtolower($current->Collation);
                if ($normalizedCollation !== $collation) {
                    $retVal[$current->Field] = (array)$current;
                }
            }
        }
        return $retVal;
    }

    /**
     * Retrieve (and statically cache) table status information.
     *
     * @return array
     */
    public function getTableStatus()
    {
        static $status = false;
        if (!$status) {
            $status = $this->getAdapter()
                ->query('SHOW TABLE STATUS', DbAdapter::QUERY_MODE_EXECUTE)
                ->toArray();
        }
        return $status;
    }

    /**
     * Check whether the actual table charset and collation match the expected
     * ones; return false if there is no problem, the desired character set and
     * collation otherwise.
     *
     * @param array $table Information about a table (from getTableStatus())
     *
     * @return bool|string
     */
    protected function getCharsetAndCollationProblemsForTable($table)
    {
        if (!isset($this->dbCommands[$table['Name']][0])) {
            return false;
        }
        $match = preg_match(
            '/(CHARSET|CHARACTER SET)[\s=]+(utf8(mb4)?)/',
            $this->dbCommands[$table['Name']][0],
            $matches
        );
        if (!$match) {
            return false;
        }
        $charset = $matches[2];
        // Check collation:
        $match = preg_match(
            '/COLLATE[\s=]+(\w+)/',
            $this->dbCommands[$table['Name']][0],
            $matches
        );
        if (!$match) {
            return false;
        }
        $collation = $matches[1];
        // The table definition does not include character set, but collation must
        // begin with the character set name, so take it from there
        // (See https://dev.mysql.com/doc/refman/8.0/en/show-table-status.html for
        // more information):
        [$tableCharset] = explode('_', $table['Collation']);
        $problemColumns = $this->getCharsetAndCollationProblemsForTableColumns(
            $table['Name'],
            $collation
        );
        if (
            strcasecmp($collation, $table['Collation']) !== 0
            || strcasecmp($charset, $tableCharset) !== 0
            || !empty($problemColumns)
        ) {
            return compact('charset', 'collation', 'problemColumns');
        }
        return false;
    }

    /**
     * Get information on character set and collation problems. Return value is an
     * associative array of table name => correct character set and collation values.
     *
     * @throws \Exception
     * @return array
     */
    public function getCharsetAndCollationProblems()
    {
        // Load details:
        $retVal = [];
        foreach ($this->getTableStatus() as $current) {
            if ($problem = $this->getCharsetAndCollationProblemsForTable($current)) {
                $retVal[$current['Name']] = $problem;
            }
        }
        return $retVal;
    }

    /**
     * Fix character set and collation problems based on the output of
     * getCharsetAndCollationProblems().
     *
     * @param array $tables Output of getCharsetAndCollationProblems()
     * @param bool  $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function fixCharsetAndCollationProblems($tables, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($tables as $table => $newSettings) {
            foreach ($newSettings['problemColumns'] as $column => $details) {
                // Set up default:
                if (null !== $details['Default']) {
                    $safeDefault = $this->getAdapter()->getPlatform()
                        ->quoteValue($details['Default']);
                    $currentDefault = " DEFAULT {$safeDefault}";
                } else {
                    $currentDefault = '';
                }

                // Change column to appropriate character encoding:
                $sql = "ALTER TABLE `$table` MODIFY `$column` " . $details['Type']
                    . ' COLLATE ' . $newSettings['collation']
                    . (strtoupper($details['Null']) == 'NO' ? ' NOT NULL' : '')
                    . $currentDefault
                    . ';';
                $sqlcommands .= $this->query($sql, $logsql);
            }
            // Adjust table character set and collation:
            $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET"
                . " {$newSettings['charset']} COLLATE {$newSettings['collation']};";
            $sqlcommands .= $this->query($sql, $logsql);
        }
        return $sqlcommands;
    }

    /**
     * Get information on all columns in a table, keyed by column name.
     *
     * @param string $table Table to describe.
     *
     * @throws \Exception
     * @return array
     */
    protected function getTableColumns($table)
    {
        $info = $this->getTableInfo();
        $columns = isset($info[$table]) ? $info[$table]->getColumns() : [];
        $retVal = [];
        foreach ($columns as $current) {
            $retVal[strtolower($current->getName())] = $current;
        }
        return $retVal;
    }

    /**
     * Get information on all constraints in a table, keyed by type and constraint
     * name. Primary key is double-keyed as ['primary']['primary'] to keep the
     * structure consistent (since primary keys are not explicitly named in the
     * source SQL).
     *
     * @param string $table Table to describe.
     *
     * @throws \Exception
     * @return array
     */
    protected function getTableConstraints($table)
    {
        $info = $this->getTableInfo();
        $constraints = isset($info[$table]) ? $info[$table]->getConstraints() : [];
        $retVal = [];
        foreach ($constraints as $current) {
            $fields = [
                'fields' => $current->getColumns(),
                'deleteRule' => $current->getDeleteRule(),
                'updateRule' => $current->getUpdateRule(),
            ];
            switch ($current->getType()) {
                case 'FOREIGN KEY':
                    $retVal['foreign'][$current->getName()] = $fields;
                    break;
                case 'PRIMARY KEY':
                    $retVal['primary']['primary'] = $fields;
                    break;
                case 'UNIQUE':
                    $retVal['unique'][$current->getName()] = $fields;
                    break;
                case 'CHECK':
                    // We don't get enough information from getConstraints() to
                    // handle CHECK constraints, so just ignore them for now:
                    break;
                default:
                    throw new \Exception(
                        'Unexpected constraint type: ' . $current->getType()
                    );
                    break;
            }
        }
        return $retVal;
    }

    /**
     * Get a list of missing tables in the database.
     *
     * @throws \Exception
     * @return array
     */
    public function getMissingTables()
    {
        $tables = $this->getAllTables();
        $missing = [];
        foreach (array_keys($this->dbCommands) as $table) {
            if (!in_array(trim(strtolower($table)), $tables)) {
                $missing[] = $table;
            }
        }

        // If we got this far, no tables need to be added:
        return $missing;
    }

    /**
     * Create missing tables based on the output of getMissingTables().
     *
     * @param array $tables Output of getMissingTables()
     * @param bool  $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function createMissingTables($tables, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($tables as $table) {
            $sqlcommands .= $this->query($this->dbCommands[$table][0], $logsql);
        }
        return $sqlcommands;
    }

    /**
     * Remove deprecated columns based on the output of getDeprecatedColumns().
     *
     * @param array $details Output of getDeprecatedColumns()
     * @param bool  $logsql  Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function removeDeprecatedColumns($details, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($details as $table => $columns) {
            foreach ($columns as $column) {
                $query = "ALTER TABLE `$table` DROP COLUMN `$column`;";
                $sqlcommands .= $this->query($query, $logsql);
            }
        }
        return $sqlcommands;
    }

    /**
     * Get a list of missing columns in the database tables (associative array,
     * key = table name, value = array of missing column definitions).
     *
     * @param array $missingTables List of missing tables
     *
     * @throws \Exception
     * @return array
     */
    public function getMissingColumns($missingTables = [])
    {
        $missing = [];
        $this->getTableInfo(true); // force reload of table info
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all('/^  `([^`]*)`.*$/m', $sql[0], $matches);
            $expectedColumns = $matches[1];

            // Create associative array of column name => SQL defining that column
            $columnDefinitions = [];
            foreach ($expectedColumns as $i => $name) {
                // Strip off any comments:
                $parts = explode('--', $matches[0][$i]);

                // Fix trailing whitespace/punctuation:
                $columnDefinitions[$name] = trim(trim($parts[0]), ',;');
            }

            // Now check for missing columns and build our return array:
            $actualColumns = array_keys($this->getTableColumns($table));
            foreach ($expectedColumns as $column) {
                if (!in_array(strtolower($column), $actualColumns)) {
                    if (!isset($missing[$table])) {
                        $missing[$table] = [];
                    }
                    $missing[$table][] = $columnDefinitions[$column];
                }
            }
        }
        return $missing;
    }

    /**
     * Given a field list extracted from a MySQL table definition (e.g. `a`,`b`)
     * return an array of fields (e.g. ['a', 'b']).
     *
     * @param string $fields Field list
     *
     * @return array
     */
    protected function explodeFields($fields)
    {
        return array_map('trim', explode(',', str_replace('`', '', $fields)));
    }

    /**
     * Compare expected vs. actual constraints and return an array of SQL
     * clauses required to create the missing constraints.
     *
     * @param array $expected Expected constraints (based on mysql.sql)
     * @param array $actual   Actual constraints (pulled from database metadata)
     *
     * @return array
     */
    protected function compareConstraints($expected, $actual)
    {
        $missing = [];
        foreach ($expected as $type => $constraints) {
            foreach ($constraints as $constraint) {
                $matchFound = false;
                foreach ($actual[$type] ?? [] as $existing) {
                    $diffCount = count(
                        array_diff($constraint['fields'], $existing['fields'])
                    ) + count(
                        array_diff($existing['fields'], $constraint['fields'])
                    );
                    if ($diffCount == 0) {
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound) {
                    $missing[] = trim(rtrim($constraint['sql'], ','));
                }
            }
        }
        return $missing;
    }

    /**
     * Get a list of missing constraints in the database tables (associative array,
     * key = table name, value = array of missing constraint definitions).
     *
     * @param array $missingTables List of missing tables
     *
     * @throws \Exception
     * @return array
     */
    public function getMissingConstraints($missingTables = [])
    {
        $missing = [];
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all(
                '/^  PRIMARY KEY \(`([^)]*)`\).*$/m',
                $sql[0],
                $primaryMatches
            );
            preg_match_all(
                '/^  CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^)]*)`\).*$/m',
                $sql[0],
                $foreignKeyMatches
            );
            preg_match_all(
                '/^  UNIQUE KEY `([^`]+)`.*\(`([^)]*)`\).*$/m',
                $sql[0],
                $uniqueMatches
            );
            $expectedConstraints = [
                'primary' => [
                    'primary' => [
                        'sql' => $primaryMatches[0][0],
                        'fields' => $this->explodeFields($primaryMatches[1][0]),
                    ],
                ],
            ];
            foreach ($uniqueMatches[0] as $i => $sql) {
                $expectedConstraints['unique'][$uniqueMatches[1][$i]] = [
                    'sql' => $sql,
                    'fields' => $this->explodeFields($uniqueMatches[2][$i]),
                ];
            }
            foreach ($foreignKeyMatches[0] as $i => $sql) {
                $expectedConstraints['foreign'][$foreignKeyMatches[1][$i]] = [
                    'sql' => $sql,
                    'fields' => $this->explodeFields($foreignKeyMatches[2][$i]),
                ];
            }

            // Now check for missing columns and build our return array:
            $actualConstraints = $this->getTableConstraints($table);

            $mismatches = $this
                ->compareConstraints($expectedConstraints, $actualConstraints);
            if (!empty($mismatches)) {
                $missing[$table] = $mismatches;
            }
        }
        return $missing;
    }

    /**
     * Normalize constraint values.
     *
     * @param array $constraints Constraints to normalize
     *
     * @return array
     */
    protected function normalizeConstraints($constraints)
    {
        foreach (['deleteRule', 'updateRule'] as $key) {
            // NO ACTION and RESTRICT are equivalent in MySQL, but different
            // versions return different values. Here we normalize them to RESTRICT
            // for simplicity/consistency.
            if ($constraints[$key] == 'NO ACTION') {
                $constraints[$key] = 'RESTRICT';
            }
        }
        return $constraints;
    }

    /**
     * Compare expected vs. actual constraint actions and return an array of SQL
     * clauses required to create the modified constraints.
     *
     * @param array $expected Expected constraints (based on mysql.sql)
     * @param array $actual   Actual constraints (pulled from database metadata)
     *
     * @return array
     */
    protected function compareConstraintActions($expected, $actual)
    {
        $modified = [];
        foreach ($expected as $type => $constraints) {
            foreach ($constraints as $name => $constraint) {
                if (!isset($actual[$type][$name])) {
                    throw new \Exception(
                        "Could not find constraint '$name' in actual constraints"
                    );
                }
                $actualConstr = $this->normalizeConstraints($actual[$type][$name]);
                if (
                    $constraint['deleteRule'] !== $actualConstr['deleteRule']
                    || $constraint['updateRule'] !== $actualConstr['updateRule']
                ) {
                    $modified[$name] = $constraint;
                }
            }
        }
        return $modified;
    }

    /**
     * Support method for getModifiedConstraints() -- check if the current constraint
     * is in the missing constraint list so we can avoid modifying something that
     * does not exist.
     *
     * @param string $constraint Column to check
     * @param array  $missing    Missing constraint list for constraint's table.
     *
     * @return bool
     */
    public function constraintIsMissing($constraint, $missing)
    {
        foreach ($missing as $current) {
            preg_match('/^\s*CONSTRAINT\s*`([^`]*)`.*$/', $current, $matches);
            if ($constraint == $matches[1]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a list of modified constraints in the database tables (associative array,
     * key = table name, value = array of modified constraint definitions).
     *
     * @param array $missingTables      List of missing tables
     * @param array $missingConstraints List of missing constraints
     *
     * @throws \Exception
     * @return array
     */
    public function getModifiedConstraints(
        $missingTables = [],
        $missingConstraints = []
    ) {
        $modified = [];
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            $expectedConstraints = [];

            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all(
                '/^\s*CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^)]*)`\)(.*)$/m',
                $sql[0],
                $foreignKeyMatches
            );
            foreach ($foreignKeyMatches[0] as $i => $sql) {
                $fkName = $foreignKeyMatches[1][$i];
                // Skip constraint if we're logging and it's missing
                if (
                    isset($missingConstraints[$table])
                    && $this->constraintIsMissing(
                        $fkName,
                        $missingConstraints[$table]
                    )
                ) {
                    continue;
                }

                $deleteRule = 'RESTRICT';
                $updateRule = 'RESTRICT';
                $options = 'RESTRICT|CASCADE|SET NULL|NO ACTION|SET DEFAULT';
                $actions = $foreignKeyMatches[3][$i] ?? '';
                if (preg_match("/ON DELETE ($options)/", $actions, $matches)) {
                    $deleteRule = $matches[1];
                }
                if (preg_match("/ON UPDATE ($options)/", $actions, $matches)) {
                    $updateRule = $matches[1];
                }

                // Fix trailing whitespace/punctuation:
                $sql = trim(trim($sql), ',;');

                $expectedConstraints['foreign'][$fkName] = [
                    'sql' => $sql,
                    'fields' => $this->explodeFields($foreignKeyMatches[2][$i]),
                    'deleteRule' => $deleteRule,
                    'updateRule' => $updateRule,
                ];
            }

            // Now check for missing columns and build our return array:
            $actualConstraints = $this->getTableConstraints($table);

            $mismatches = $this
                ->compareConstraintActions($expectedConstraints, $actualConstraints);
            if (!empty($mismatches)) {
                $modified[$table]['foreign'] = $mismatches;
            }
        }
        return $modified;
    }

    /**
     * Given a current row default, return true if the current default matches the
     * one found in the SQL provided as the $sql parameter. Return false if there
     * is a mismatch that will require table structure updates.
     *
     * @param string $currentDefault Object to check
     * @param string $sql            SQL to compare against
     *
     * @return bool
     */
    protected function defaultMatches($currentDefault, $sql)
    {
        // Normalize current default:
        if ($currentDefault && strtoupper($currentDefault) === 'NULL') {
            $currentDefault = null;
        }
        if (null !== $currentDefault) {
            $currentDefault = trim($currentDefault, "'");
            if (strtoupper($currentDefault) === 'CURRENT_TIMESTAMP()') {
                $currentDefault = 'CURRENT_TIMESTAMP';
            }
        }

        preg_match('/.* DEFAULT (.*)$/', $sql, $matches);
        $expectedDefault = $matches[1] ?? null;
        if (null !== $expectedDefault) {
            $expectedDefault = trim(rtrim($expectedDefault, ','), "'");
            $expectedDefault = (strtoupper($expectedDefault) == 'NULL')
                ? null : $expectedDefault;
        }
        return $expectedDefault === $currentDefault;
    }

    /**
     * Given a current row default, return true if the current nullability matches
     * the one found in the SQL provided as the $sql parameter. Return false if there
     * is a mismatch that will require table structure updates.
     *
     * @param bool   $currentNullable Current nullability
     * @param string $sql             SQL to compare against
     *
     * @return bool
     */
    protected function nullableMatches(bool $currentNullable, string $sql): bool
    {
        $expectedNullable = stripos($sql, 'NOT NULL') ? false : true;
        return $expectedNullable === $currentNullable;
    }

    /**
     * Given a table column object, return true if the object's type matches the
     * specified $type parameter. Return false if there is a mismatch that will
     * require table structure updates.
     *
     * @param \Laminas\Db\Metadata\Object\ColumnObject $column       Object to check
     * @param string                                   $expectedType Type to compare
     *
     * @return bool
     */
    protected function typeMatches($column, $expectedType)
    {
        // Get base type:
        $type = $column->getDataType();

        // If it's not a blob or a text (which don't have explicit sizes in our SQL),
        // we should see what the character length is, if any:
        if (
            $type != 'blob' && $type != 'text' && $type !== 'mediumtext'
            && $type != 'longtext' && $type != 'json'
        ) {
            $charLen = $column->getCharacterMaximumLength();
            if ($charLen) {
                $type .= '(' . $charLen . ')';
            }
        }

        // If it's an integer, the expected type will have a parenthetical value;
        // this is a display width which we can't retrieve using the column metadata
        // object.  Since display width is not important to VuFind, we should ignore
        // this factor when comparing things.
        if (
            $type == 'int' || $type == 'tinyint' || $type == 'smallint'
            || $type == 'mediumint' || $type == 'bigint'
        ) {
            [$expectedType] = explode('(', $expectedType);
        }

        // Some versions of MariaDB store json fields as longtext, while MySQL
        // actually has an explicit json type. We need a special case to handle
        // this inconsistency. See: https://mariadb.com/kb/en/json-data-type/
        return $type == $expectedType
            || ($type === 'longtext' && $expectedType === 'json');
    }

    /**
     * Parse keys from a "create table" statement
     *
     * @param string $createSql Create table statement
     *
     * @return array
     */
    protected function parseKeysFromCreateTable(string $createSql): array
    {
        $keys = [];
        // Parse key names etc. out of the CREATE TABLE SQL, which will always be
        // the first entry in the array; we assume the standard mysqldump
        // formatting is used here.
        preg_match_all(
            '/^\s*(UNIQUE\s+)?KEY `([^`]+)` \((.+)\),?$/m',
            $createSql,
            $keyMatches
        );
        foreach (array_keys($keyMatches[0]) as $i) {
            $unique = !empty($keyMatches[1][$i]);
            $name = $keyMatches[2][$i];
            // Normalize trailing whitespace and spaces after commas:
            $definition = preg_replace('/,\s+`/', ',`', trim($keyMatches[3][$i]));

            $keys[$name] = compact('unique', 'definition');
        }
        return $keys;
    }

    /**
     * Support method for getModifiedColumns() -- check if the current column is
     * in the missing column list so we can avoid modifying something that does
     * not exist.
     *
     * @param string $column  Column to check
     * @param array  $missing Missing column list for column's table.
     *
     * @return bool
     */
    public function columnIsMissing($column, $missing)
    {
        foreach ($missing as $current) {
            preg_match('/^\s*`([^`]*)`.*$/', $current, $matches);
            if ($column == $matches[1]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a list of deprecated columns found in the database.
     *
     * @return array
     */
    public function getDeprecatedColumns()
    {
        $result = [];
        foreach ($this->deprecatedColumns as $table => $columns) {
            $tableData = $this->getTableColumns(($table));
            foreach ($columns as $column) {
                if (isset($tableData[$column])) {
                    $result[$table] = array_merge($result[$table] ?? [], [$column]);
                }
            }
        }
        return $result;
    }

    /**
     * Get a list of changed columns in the database tables (associative array,
     * key = table name, value = array of column name => new data type).
     *
     * @param array $missingTables  List of missing tables
     * @param array $missingColumns List of missing columns
     *
     * @throws \Exception
     * @return array
     */
    public function getModifiedColumns(
        $missingTables = [],
        $missingColumns = []
    ) {
        $modified = [];
        $this->getTableInfo(true); // force reload of table info
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all(
                '/^  `([^`]*)`\s+([^\s,]+)[\t ,]+.*$/m',
                $sql[0],
                $matches
            );
            $expectedColumns = array_map('strtolower', $matches[1]);
            $expectedTypes = $matches[2];

            // Create associative array of column name => SQL defining that column
            $columnDefinitions = [];
            foreach ($expectedColumns as $i => $name) {
                // Strip off any comments:
                $parts = explode('--', $matches[0][$i]);

                // Fix trailing whitespace/punctuation:
                $columnDefinitions[$name] = trim(trim($parts[0]), ',;');
            }

            // Now check for mismatched types:
            $actualColumns = $this->getTableColumns($table);
            foreach ($expectedColumns as $i => $column) {
                // Skip column if we're logging and it's missing
                if (
                    isset($missingColumns[$table])
                    && $this->columnIsMissing($column, $missingColumns[$table])
                ) {
                    continue;
                }
                $currentColumn = $actualColumns[$column];
                if (
                    !$this->typeMatches($currentColumn, $expectedTypes[$i])
                    || !$this->defaultMatches(
                        $currentColumn->getColumnDefault(),
                        $columnDefinitions[$column]
                    )
                    || !$this->nullableMatches(
                        $currentColumn->getIsNullable(),
                        $columnDefinitions[$column]
                    )
                ) {
                    if (!isset($modified[$table])) {
                        $modified[$table] = [];
                    }
                    $modified[$table][] = $columnDefinitions[$column];
                }
            }
        }
        return $modified;
    }

    /**
     * Create missing columns based on the output of getMissingColumns().
     *
     * @param array $columns Output of getMissingColumns()
     * @param bool  $logsql  Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string        SQL if $logsql is true, empty string otherwise
     */
    public function createMissingColumns($columns, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($columns as $table => $sql) {
            foreach ($sql as $column) {
                $sqlcommands .= $this->query(
                    "ALTER TABLE `{$table}` ADD COLUMN {$column}",
                    $logsql
                );
            }
        }
        return $sqlcommands;
    }

    /**
     * Create missing constraints based on the output of getMissingConstraints().
     *
     * @param array $constraints Output of getMissingConstraints()
     * @param bool  $logsql      Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string        SQL if $logsql is true, empty string otherwise
     */
    public function createMissingConstraints($constraints, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($constraints as $table => $sql) {
            foreach ($sql as $constraint) {
                $sqlcommands .= $this->query(
                    "ALTER TABLE $table ADD {$constraint};",
                    $logsql
                );
            }
        }
        return $sqlcommands;
    }

    /**
     * Modify columns based on the output of getModifiedColumns().
     *
     * @param array $columns Output of getModifiedColumns()
     * @param bool  $logsql  Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string        SQL if $logsql is true, empty string otherwise
     */
    public function updateModifiedColumns($columns, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($columns as $table => $sql) {
            foreach ($sql as $column) {
                $sqlcommands .= $this->query(
                    "ALTER TABLE `{$table}` MODIFY COLUMN {$column}",
                    $logsql
                );
            }
        }
        return $sqlcommands;
    }

    /**
     * Modify constraints based on the output of getModifiedConstraints().
     *
     * @param array $constraints Output of getModifiedConstraints()
     * @param bool  $logsql      Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string            SQL if $logsql is true, empty string otherwise
     */
    public function updateModifiedConstraints($constraints, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($constraints as $table => $constraintTypeList) {
            foreach ($constraintTypeList as $type => $constraintList) {
                if ('foreign' !== $type) {
                    throw new \Exception(
                        "Unable to handle modification of constraint type '$type'"
                    );
                }
                foreach ($constraintList as $name => $constraint) {
                    $sqlcommands .= $this->query(
                        "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`",
                        $logsql
                    );
                    $sqlcommands .= $this->query(
                        "ALTER TABLE $table ADD {$constraint['sql']}",
                        $logsql
                    );
                }
            }
        }
        return $sqlcommands;
    }

    /**
     * Get a list of modified keys in the database tables (associative array,
     * key = table name, value = array of modified key definitions).
     *
     * @param array $missingTables List of missing tables
     *
     * @throws \Exception
     * @return array
     */
    public function getModifiedKeys(array $missingTables = []): array
    {
        $modified = [];
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            $expectedKeys = $this->parseKeysFromCreateTable($sql[0]);

            $result = $this->getAdapter()->query(
                "SHOW CREATE TABLE $table",
                DbAdapter::QUERY_MODE_EXECUTE
            )->current();
            $resultArray = $result ? $result->getArrayCopy() : [''];
            $actualCreateSQL = end($resultArray);
            $actualKeys = $this->parseKeysFromCreateTable($actualCreateSQL);

            // Create lists of keys to drop and add:
            $add = [];
            // Should we want to drop any keys not found in our database definition
            // so that it would be possible to e.g. drop columns if necessary, the
            // following line could be used:
            //$drop = array_diff(array_keys($actualKeys), array_keys($expectedKeys));
            $drop = [];
            foreach ($expectedKeys as $name => $expected) {
                if (!isset($actualKeys[$name])) {
                    $add[$name] = $expected;
                } elseif (
                    $actualKeys[$name]['unique'] !== $expected['unique']
                    || $actualKeys[$name]['definition'] !== $expected['definition']
                ) {
                    $drop[] = $name;
                    $add[$name] = $expected;
                }
            }
            if ($add || $drop) {
                $modified[$table] = compact('add', 'drop');
            }
        }
        return $modified;
    }

    /**
     * Update keys based on the output of getModifiedKeys().
     *
     * @param array $tables Output of getModifiedKeys()
     * @param bool  $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function updateModifiedKeys($tables, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($tables as $table => $newSettings) {
            foreach ($newSettings['drop'] as $key) {
                $sql = "ALTER TABLE `$table` DROP KEY `$key`";
                $sqlcommands .= $this->query($sql, $logsql);
            }
            foreach ($newSettings['add'] as $keyName => $keyDetails) {
                $sql = "ALTER TABLE `$table` ADD "
                    . ($keyDetails['unique'] ? 'UNIQUE ' : '')
                    . "KEY `$keyName` ({$keyDetails['definition']})";
                $sqlcommands .= $this->query($sql, $logsql);
            }
        }
        return $sqlcommands;
    }
}
