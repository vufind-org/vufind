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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Zend action helper to perform database upgrades
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DbUpgrade extends AbstractPlugin
{
    protected $dbCommands = array();

    /**
     * Given a SQL file, parse it for table creation commands.
     *
     * @param string $file Filename to load.
     *
     * @return void
     */
    public function loadSql($file)
    {
        $sql = file_get_contents(APPLICATION_PATH . '/module/VuFind/sql/mysql.sql');
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
                    $this->dbCommands[$table] = array();
                }
                $this->dbCommands[$table][] = $statement;
            }
        }
    }

    /**
     * Get a list of all tables in the database.
     *
     * @throws Exception
     * @return array
     */
    public function getAllTables()
    {
        /* TODO
        // Load the default database adapter (by this point, it should be valid):
        $db = Zend_Db_Table::getDefaultAdapter();
        if (!is_object($db)) {
            throw new \Exception('Could not load default database adapter.');
        }

        // Get a list of all tables in the database:
        $results = $db->query("SHOW TABLES;");
        $tmp = $results->fetchAll();
        $tables = array();
        foreach ($tmp as $current) {
            $keys = array_keys($current);
            $tables[] = trim(strtolower($current[$keys[0]]));
        }
        return $tables;
         */
    }

    /**
     * Get information on all columns in a table, keyed by column name.
     *
     * @param string $table Table to describe.
     *
     * @throws Exception
     * @return array
     */
    public function getTableColumns($table)
    {
        /* TODO
        // Load the default database adapter (by this point, it should be valid):
        $db = Zend_Db_Table::getDefaultAdapter();
        if (!is_object($db)) {
            throw new \Exception('Could not load default database adapter.');
        }

        // Get a list of all tables in the database:
        $results = $db->query("DESCRIBE `$table`;");
        $tmp = $results->fetchAll();
        $columns = array();
        foreach ($tmp as $current) {
            $columns[trim(strtolower($current['Field']))] = $current;
        }
        return $columns;
         */
    }

    /**
     * Get a list of missing tables in the database.
     *
     * @throws Exception
     * @return array
     */
    public function getMissingTables()
    {
        $tables = $this->getAllTables();
        $missing = array();
        foreach ($this->dbCommands as $table => $sql) {
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
     * @param array  $tables Output of getMissingTables()
     * @param object $db     Database connection with CREATE permission
     *
     * @throws Exception
     * @return void
     */
    public function createMissingTables($tables, $db)
    {
        foreach ($tables as $table) {
            $db->query($this->dbCommands[$table][0]);
        }
    }

    /**
     * Get a list of missing columns in the database tables (associative array,
     * key = table name, value = array of missing column definitions).
     *
     * @throws Exception
     * @return array
     */
    public function getMissingColumns()
    {
        $missing = array();
        foreach ($this->dbCommands as $table => $sql) {
            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all('/^  `([^`]*)`.*$/m', $sql[0], $matches);
            $expectedColumns = $matches[1];

            // Create associative array of column name => SQL defining that column
            $columnDefinitions = array();
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
                        $missing[$table] = array();
                    }
                    $missing[$table][] = $columnDefinitions[$column];
                }
            }
        }
        return $missing;
    }

    /**
     * Get a list of changed columns in the database tables (associative array,
     * key = table name, value = array of column name => new data type).
     *
     * @throws Exception
     * @return array
     */
    public function getModifiedColumns()
    {
        $missing = array();
        foreach ($this->dbCommands as $table => $sql) {
            // Parse column names out of the CREATE TABLE SQL, which will always be
            // the first entry in the array; we assume the standard mysqldump
            // formatting is used here.
            preg_match_all(
                '/^  `([^`]*)`\s+([^\s,]+)[\s,]+.*$/m', $sql[0],
                $matches
            );
            $expectedColumns = array_map('strtolower', $matches[1]);
            $expectedTypes = $matches[2];

            // Create associative array of column name => SQL defining that column
            $columnDefinitions = array();
            foreach ($expectedColumns as $i => $name) {
                // Strip off any comments:
                $parts = explode('--', $matches[0][$i]);

                // Fix trailing whitespace/punctuation:
                $columnDefinitions[$name] = trim(trim($parts[0]), ',;');
            }

            // Now check for mismatched types:
            $actualColumns = $this->getTableColumns($table);
            foreach ($expectedColumns as $i => $column) {
                if ($actualColumns[$column]['Type'] != $expectedTypes[$i]) {
                    if (!isset($missing[$table])) {
                        $missing[$table] = array();
                    }
                    $missing[$table][] = $columnDefinitions[$column];
                }
            }
        }
        return $missing;
    }

    /**
     * Create missing columns based on the output of getMissingColumns().
     *
     * @param array  $columns Output of getMissingColumns()
     * @param object $db      Database connection with ALTER permission
     *
     * @throws Exception
     * @return void
     */
    public function createMissingColumns($columns, $db)
    {
        foreach ($columns as $table => $sql) {
            foreach ($sql as $column) {
                $db->query("ALTER TABLE `{$table}` ADD COLUMN {$column}");
            }
        }
    }

    /**
     * Modify columns based on the output of getModifiedColumns().
     *
     * @param array  $columns Output of getModifiedColumns()
     * @param object $db      Database connection with ALTER permission
     *
     * @throws Exception
     * @return void
     */
    public function updateModifiedColumns($columns, $db)
    {
        foreach ($columns as $table => $sql) {
            foreach ($sql as $column) {
                $db->query("ALTER TABLE `{$table}` MODIFY COLUMN {$column}");
            }
        }
    }
}