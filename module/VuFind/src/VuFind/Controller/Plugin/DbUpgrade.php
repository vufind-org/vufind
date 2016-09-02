<?php
/**
 * VuFind Action Helper - Database upgrade tools
 *
 * PHP version 5
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Controller\Plugin;
use Zend\Db\Adapter\Adapter as DbAdapter, Zend\Db\Metadata\Metadata as DbMetadata,
    Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Zend action helper to perform database upgrades
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
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
                $statement, $matches
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
            $metadata = new DbMetadata($this->getAdapter());
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
     * Support method for getEncodingProblems() -- get column details
     *
     * @param string $table Table to check
     *
     * @throws \Exception
     * @return array
     */
    protected function getEncodingProblemsForTable($table)
    {
        // Get column summary:
        $sql = "SHOW FULL COLUMNS FROM `{$table}`";
        $results = $this->getAdapter()->query($sql, DbAdapter::QUERY_MODE_EXECUTE);

        // Load details:
        $retVal = [];
        foreach ($results as $current) {
            if (strtolower(substr($current->Collation, 0, 6)) == 'latin1') {
                $retVal[$current->Field] = (array)$current;
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
     * Check whether the actual table collation matches the expected table
     * collation; return false if there is no problem, the name of the desired
     * collation otherwise.
     *
     * @param array $table Information about a table (from getTableStatus())
     *
     * @return bool|string
     */
    protected function getCollationProblemsForTable($table)
    {
        // For now, we'll only detect problems in utf8-encoded tables; if the
        // user has a Latin1 database, they probably have more complex issues to
        // work through anyway.
        preg_match_all(
            '/CHARSET=utf8 COLLATE (\w+)/', $this->dbCommands[$table['Name']][0],
            $matches
        );
        if (isset($matches[1][0])
            && strtolower($matches[1][0]) != strtolower($table['Collation'])
        ) {
            return $matches[1][0];
        }
        return false;
    }

    /**
     * Get information on incorrectly collated tables/columns. Return value is
     * associative array of table name => correct collation value.
     *
     * @throws \Exception
     * @return array
     */
    public function getCollationProblems()
    {
        // Load details:
        $retVal = [];
        foreach ($this->getTableStatus() as $current) {
            if ($problem = $this->getCollationProblemsForTable($current)) {
                $retVal[$current['Name']] = $problem;
            }
        }
        return $retVal;
    }

    /**
     * Fix collation problems based on the output of getCollationProblems().
     *
     * @param array $tables Output of getCollationProblems()
     * @param bool  $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function fixCollationProblems($tables, $logsql = false)
    {
        $sqlcommands = '';
        foreach ($tables as $table => $newCollation) {
            // Adjust default table collation:
            $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8 "
                . "COLLATE $newCollation;";
            $sqlcommands .= $this->query($sql, $logsql);
        }
        return $sqlcommands;
    }

    /**
     * Get information on incorrectly encoded tables/columns.
     *
     * @throws \Exception
     * @return array
     */
    public function getEncodingProblems()
    {
        // Load details:
        $retVal = [];
        foreach ($this->getTableStatus() as $current) {
            if (strtolower(substr($current['Collation'], 0, 6)) == 'latin1') {
                $retVal[$current['Name']]
                    = $this->getEncodingProblemsForTable($current['Name']);
            }
        }

        return $retVal;
    }

    /**
     * Fix encoding problems based on the output of getEncodingProblems().
     *
     * @param array $tables Output of getEncodingProblems()
     * @param bool  $logsql Should we return the SQL as a string rather than
     * execute it?
     *
     * @throws \Exception
     * @return string       SQL if $logsql is true, empty string otherwise
     */
    public function fixEncodingProblems($tables, $logsql = false)
    {
        $newCollation = "utf8_general_ci";
        $sqlcommands = '';

        // Database conversion routines inspired by:
        //     https://github.com/nicjansma/mysql-convert-latin1-to-utf8
        foreach ($tables as $table => $columns) {
            foreach ($columns as $column => $details) {
                $oldType = $details['Type'];
                $parts = explode('(', $oldType);
                switch ($parts[0]) {
                case 'char':
                    $newType = 'binary(' . $parts[1];
                    break;
                case 'text':
                    $newType = 'blob';
                    break;
                case 'varchar':
                    $newType = 'varbinary(' . $parts[1];
                    break;
                default:
                    throw new \Exception('Unexpected column type: ' . $parts[0]);
                }
                // Set up default:
                if (null !== $details['Default']) {
                    $safeDefault = $this->getAdapter()->getPlatform()
                        ->quoteValue($details['Default']);
                    $currentDefault = " DEFAULT {$safeDefault}";
                } else {
                    $currentDefault = '';
                }

                // Change to binary equivalent:
                $sql = "ALTER TABLE `$table` MODIFY `$column` $newType"
                    . (strtoupper($details['Null']) == 'NO' ? ' NOT NULL' : '')
                    . $currentDefault
                    . ";";
                $sqlcommands .= $this->query($sql, $logsql);

                // Change back to appropriate character data with fixed encoding:
                $sql = "ALTER TABLE `$table` MODIFY `$column` $oldType"
                    . " COLLATE $newCollation"
                    . (strtoupper($details['Null']) == 'NO' ? ' NOT NULL' : '')
                    . $currentDefault
                    . ";";
                $sqlcommands .= $this->query($sql, $logsql);
            }

            // Adjust default table collation:
            $sql = "ALTER TABLE `$table` DEFAULT COLLATE $newCollation;";
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
        $info = $this->getTableInfo(true);
        $columns = isset($info[$table]) ? $info[$table]->getColumns() : [];
        $retVal = [];
        foreach ($columns as $current) {
            $retVal[strtolower($current->getName())] = $current;
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
        preg_match("/.* DEFAULT (.*)$/", $sql, $matches);
        $expectedDefault = isset($matches[1]) ? $matches[1] : null;
        if (null !== $expectedDefault) {
            $expectedDefault = trim(rtrim($expectedDefault, ','), "'");
            $expectedDefault = (strtoupper($expectedDefault) == 'NULL')
                ? null : $expectedDefault;
        }
        return ($expectedDefault === $currentDefault);
    }

    /**
     * Given a table column object, return true if the object's type matches the
     * specified $type parameter.  Return false if there is a mismatch that will
     * require table structure updates.
     *
     * @param \Zend\Db\Metadata\Object\ColumnObject $column       Object to check
     * @param string                                $expectedType Type to compare
     *
     * @return bool
     */
    protected function typeMatches($column, $expectedType)
    {
        // Get base type:
        $type = $column->getDataType();

        // If it's not a blob or a text (which don't have explicit sizes in our SQL),
        // we should see what the character length is, if any:
        if ($type != 'blob' && $type != 'text' && $type != 'longtext') {
            $charLen = $column->getCharacterMaximumLength();
            if ($charLen) {
                $type .= '(' . $charLen . ')';
            }
        }

        // If it's an integer, the expected type will have a parenthetical value;
        // this is a display width which we can't retrieve using the column metadata
        // object.  Since display width is not important to VuFind, we should ignore
        // this factor when comparing things.
        if ($type == 'int' || $type == 'tinyint' || $type == 'smallint'
            || $type == 'mediumint' || $type == 'bigint'
        ) {
            list($expectedType) = explode('(', $expectedType);
        }

        return $type == $expectedType;
    }

    /**
     * Support method for getModifiedColumns() -- check if the current column is
     * in the missing column list so we can avoid modifying something that does
     * not exist.
     *
     * @param string $column  Column to check
     * @param string $missing Missing column list for column's table.
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
     * Get a list of changed columns in the database tables (associative array,
     * key = table name, value = array of column name => new data type).
     *
     * @param array $missingTables  List of missing tables
     * @param array $missingColumns List of missing columns
     *
     * @throws \Exception
     * @return array
     */
    public function getModifiedColumns($missingTables = [],
        $missingColumns = []
    ) {
        $modified = [];
        foreach ($this->dbCommands as $table => $sql) {
            // Skip missing tables if we're logging
            if (in_array($table, $missingTables)) {
                continue;
            }

            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all(
                '/^  `([^`]*)`\s+([^\s,]+)[\t ,]+.*$/m', $sql[0],
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
                if (isset($missingColumns[$table])
                    && $this->columnIsMissing($column, $missingColumns[$table])
                ) {
                    continue;
                }
                $currentColumn = $actualColumns[$column];
                if (!$this->typeMatches($currentColumn, $expectedTypes[$i])
                    || !$this->defaultMatches(
                        $currentColumn->getColumnDefault(),
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
                    "ALTER TABLE `{$table}` ADD COLUMN {$column}", $logsql
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
                    "ALTER TABLE `{$table}` MODIFY COLUMN {$column}", $logsql
                );
            }
        }
        return $sqlcommands;
    }
}
