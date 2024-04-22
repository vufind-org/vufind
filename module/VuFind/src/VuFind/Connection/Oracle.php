<?php

/**
 * Oracle support code for VTLS Virtua Driver
 *
 * PHP version 8
 *
 * Copyright (C) University of Southern Queensland 2008.
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
 * @package  Oracle
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Connection;

use function count;
use function is_array;

/**
 * Oracle support code for VTLS Virtua Driver
 *
 * @category VuFind
 * @package  Oracle
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Oracle
{
    /**
     * Database Handle
     *
     * @var resource
     */
    protected $dbHandle;

    /**
     * Error information - message
     *
     * @var string
     */
    protected $lastError;

    /**
     * Error information - type
     *
     * @var string
     */
    protected $lastErrorType;

    /**
     * Error information - bind params
     *
     * @var array
     */
    protected $lastErrorFields;

    /**
     * Error information - SQL attempted
     *
     * @var string
     */
    protected $lastSql;

    /**
     * Constructor -- connect to database.
     *
     * @param string $username Username for connection
     * @param string $password Password for connection
     * @param string $tns      TNS specification for connection
     */
    public function __construct($username, $password, $tns)
    {
        $this->clearError();
        $tmp = error_reporting(1);
        if ($this->dbHandle = @oci_connect($username, $password, $tns)) {
            error_reporting($tmp);
        } else {
            error_reporting($tmp);
            $this->handleError('connect', oci_error());
            throw new \Exception('Oracle connection problem.');
        }
    }

    /**
     * Get access to the Oracle handle.
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->dbHandle;
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        // Close the OCI connection unless we failed to establish it:
        if ($this->dbHandle !== false) {
            oci_close($this->dbHandle);
        }
    }

    /**
     * Wrapper around oci_parse.
     *
     * @param string $sql SQL statement to prepare.
     *
     * @return mixed      SQL resource on success, boolean false otherwise.
     */
    public function prepare($sql)
    {
        if ($parsed = @oci_parse($this->dbHandle, $sql)) {
            return $parsed;
        } else {
            $this->handleError('parsing', oci_error($this->dbHandle), $sql);
            return false;
        }
    }

    /**
     * Wrapper around oci_new_descriptor.
     *
     * @return mixed New descriptor on success, boolean false otherwise.
     */
    public function prepRowId()
    {
        if ($new_id = @oci_new_descriptor($this->dbHandle, OCI_D_ROWID)) {
            return $new_id;
        } else {
            $this->handleError('new_descriptor', oci_error($this->dbHandle));
            return false;
        }
    }

    /**
     * Convert data type name into constant
     *
     * @param string $data_type Data type (string, integer, float, long, date,
     * row_id, clob, or blob)
     *
     * @return int
     */
    protected function getDataTypeConstant($data_type)
    {
        switch ($data_type) {
            case 'integer':
                return SQLT_INT;
            case 'float':
                return SQLT_FLT;
            case 'long':
                return SQLT_LNG;
            case 'row_id':
                return SQLT_RDD;
            case 'clob':
                return SQLT_CLOB;
            case 'blob':
                return SQLT_BLOB;
            case 'string':
            case 'date':
            default:
                // Date and string are redundant since default is varchar,
                //  but they're here for clarity.
                return SQLT_CHR;
        }
    }

    /**
     * Wrapper around oci_bind_by_name.
     *
     * @param resource $parsed       Result returned by prepare() method.
     * @param string   $place_holder The colon-prefixed bind variable placeholder
     * used in the statement.
     * @param string   $data         The PHP variable to be associated with
     * $place_holder
     * @param string   $data_type    The type of $data (string, integer, float,
     * long, date, row_id, clob, or blob)
     * @param int      $length       Sets the maximum length for the data. If you
     * set it to -1, this function will use the current length of variable to set
     * the maximum length.
     *
     * @return bool
     */
    public function bindParam(
        $parsed,
        $place_holder,
        $data,
        $data_type = 'string',
        $length = -1
    ) {
        $success = @oci_bind_by_name(
            $parsed,
            $place_holder,
            $data,
            $length,
            $this->getDataTypeConstant($data_type)
        );
        if ($success) {
            return true;
        } else {
            $this->handleError('binding', oci_error());
            return false;
        }
    }

    /**
     * Same as bindParam(), but variable is parsed by reference to allow for correct
     * functioning of the 'RETURNING' sql statement. Annoying, but putting it in two
     * separate functions allows the user to pass string literals into bindParam
     * without a fatal error.
     *
     * @param resource $parsed       Result returned by prepare() method.
     * @param string   $place_holder The colon-prefixed bind variable placeholder
     * used in the statement.
     * @param string   $data         The PHP variable to be associated with
     * $place_holder
     * @param string   $data_type    The type of $data (string, integer, float,
     * long, date, row_id, clob, or blob)
     * @param int      $length       Sets the maximum length for the data. If you
     * set it to -1, this function will use the current length of variable to set
     * the maximum length.
     *
     * @return bool
     */
    public function returnParam(
        $parsed,
        $place_holder,
        &$data,
        $data_type = 'string',
        $length = -1
    ) {
        $success = @oci_bind_by_name(
            $parsed,
            $place_holder,
            $data,
            $length,
            $this->getDataTypeConstant($data_type)
        );
        if ($success) {
            return true;
        } else {
            $this->handleError('binding', oci_error());
            return false;
        }
    }

    /**
     * Wrapper around oci_execute.
     *
     * @param resource $parsed Result returned by prepare() method.
     *
     * @return bool
     */
    public function exec($parsed)
    {
        // OCI_DEFAULT == DO NOT COMMIT!!!
        if (@oci_execute($parsed, OCI_DEFAULT)) {
            return true;
        } else {
            $this->handleError('executing', oci_error($parsed));
            return false;
        }
    }

    /**
     * Wrapper around oci_commit.
     *
     * @return bool
     */
    public function commit()
    {
        if (@oci_commit($this->dbHandle)) {
            return true;
        } else {
            $this->handleError('commit', oci_error($this->dbHandle));
            return false;
        }
    }

    /**
     * Wrapper around oci_rollback.
     *
     * @return bool
     */
    public function rollback()
    {
        if (@oci_rollback($this->dbHandle)) {
            return true;
        } else {
            $this->handleError('rollback', oci_error($this->dbHandle));
            return false;
        }
    }

    /**
     * Wrapper around oci_free_statement.
     *
     * @param resource $parsed Result returned by prepare() method.
     *
     * @return bool
     */
    public function free($parsed)
    {
        if (@oci_free_statement($parsed)) {
            return true;
        } else {
            $this->handleError('free', oci_error($this->dbHandle));
            return false;
        }
    }

    /**
     * Execute a SQL statement and return the results.
     *
     * @param string $sql    SQL to execute
     * @param array  $fields Bind parameters (optional)
     *
     * @return array|bool    Results on success, false on error.
     */
    public function simpleSelect($sql, $fields = [])
    {
        $stmt = $this->prepare($sql);
        foreach ($fields as $field => $datum) {
            [$column, $type] = explode(':', $field);
            $this->bindParam($stmt, ':' . $column, $datum, $type);
        }

        if ($this->exec($stmt)) {
            oci_fetch_all($stmt, $return_array, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
            $this->free($stmt);
            return $return_array;
        } else {
            $this->lastErrorFields = $fields;
            $this->free($stmt);
            return false;
        }
    }

    /**
     * Delete row(s) from a table.
     *
     * @param string $table  Table to update.
     * @param array  $fields Fields to use to match rows to delete.
     *
     * @return bool
     */
    public function simpleDelete($table, $fields = [])
    {
        $types   = [];
        $data    = [];
        $clauses = [];

        // Split all the fields up into arrays
        foreach ($fields as $field => $datum) {
            [$column, $type] = explode(':', $field);
            $types[$column] = $type;
            $data[$column]  = $datum;
            $clauses[]      = "$column = :$column";
        }

        // Prepare the SQL for child table - turn the columns in placeholders for
        // the bind
        $sql  = "DELETE FROM $table WHERE " . implode(' AND ', $clauses);
        $delete = $this->prepare($sql);

        // Bind Variables
        foreach (array_keys($data) as $column) {
            $this->bindParam(
                $delete,
                ':' . $column,
                $data[$column],
                $types[$column]
            );
        }

        // Execute
        if ($this->exec($delete)) {
            $this->commit();
            $this->free($delete);
            return true;
        } else {
            $this->lastErrorFields = $fields;
            $this->free($delete);
            return false;
        }
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table  Table to append to.
     * @param array  $fields Data to write to table.
     *
     * @return bool
     */
    public function simpleInsert($table, $fields = [])
    {
        $types   = [];
        $data    = [];
        $columns = [];
        $values  = [];

        // Split all the fields up into arrays
        foreach ($fields as $field => $datum) {
            $tmp = explode(':', $field);
            $column = array_shift($tmp);

            // For binding
            $types[$column] = array_shift($tmp);
            $data[$column]  = $datum;

            // For building the sql
            $columns[]      = $column;
            // Dates are special
            if (count($tmp) > 0 && null !== $datum) {
                $values[] = "TO_DATE(:$column, '" . implode(':', $tmp) . "')";
            } else {
                $values[] = ":$column";
            }
        }

        $sql  = "INSERT INTO $table (" . implode(', ', $columns) . ') VALUES (' .
            implode(', ', $values) . ')';
        $insert = $this->prepare($sql);

        // Bind Variables
        foreach (array_keys($data) as $column) {
            $this->bindParam(
                $insert,
                ':' . $column,
                $data[$column],
                $types[$column]
            );
        }

        // Execute
        if ($this->exec($insert)) {
            $this->commit();
            $this->free($insert);
            return true;
        } else {
            $this->lastErrorFields = $fields;
            $this->free($insert);
            return false;
        }
    }

    /**
     * Execute a simple SQL statement.
     *
     * @param string $sql    SQL to execute
     * @param array  $fields Bind parameters (optional)
     *
     * @return bool
     */
    public function simpleSql($sql, $fields = [])
    {
        $stmt = $this->prepare($sql);
        foreach ($fields as $field => $datum) {
            [$column, $type] = explode(':', $field);
            $this->bindParam($stmt, ':' . $column, $datum, $type);
        }
        if ($this->exec($stmt)) {
            $this->commit();
            $this->free($stmt);
            return true;
        } else {
            $this->lastErrorFields = $fields;
            $this->free($stmt);
            return false;
        }
    }

    /**
     * Clear out internal error tracking details.
     *
     * @return void
     */
    protected function clearError()
    {
        $this->lastError       = null;
        $this->lastErrorType   = null;
        $this->lastErrorFields = null;
        $this->lastSql         = null;
    }

    /**
     * Store information about an error.
     *
     * @param string $type  Type of error
     * @param string $error Detailed error message
     * @param string $sql   SQL statement that caused error
     *
     * @return void
     */
    protected function handleError($type, $error, $sql = '')
    {
        // All we are doing at the moment is storing it
        $this->lastError       = $error;
        $this->lastErrorType   = $type;
        $this->lastSql         = $sql;
    }

    /**
     * Error Retrieval -- last error message.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Error Retrieval -- last error type.
     *
     * @return string
     */
    public function getLastErrorType()
    {
        return $this->lastErrorType;
    }

    /**
     * Error Retrieval -- SQL that triggered last error.
     *
     * @return string
     */
    public function getLastSql()
    {
        return $this->lastSql;
    }

    /**
     * Error Retrieval -- full details formatted as HTML.
     *
     * @return string
     */
    public function getHtmlError()
    {
        if ($this->lastError == null) {
            return 'No error found!';
        }

        // Generic stuff
        $output  = "<b>ORACLE ERROR</b><br/>\n";
        $output .= "Oracle '" . $this->lastErrorType . "' Error<br />\n";
        $output .= "=============<br />\n";
        foreach ($this->lastError as $key => $value) {
            $output .= "($key) => $value<br />\n";
        }

        // Anything special for this error type?
        switch ($this->lastErrorType) {
            case 'parsing':
                $output .= "=============<br />\n";
                $output .= "Offset into SQL:<br />\n";
                $output .=
                    substr($this->lastError['sqltext'], $this->lastError['offset']) .
                    "\n";
                break;
            case 'executing':
                $output .= "=============<br />\n";
                $output .= "Offset into SQL:<br />\n";
                $output .=
                    substr($this->lastError['sqltext'], $this->lastError['offset']) .
                    "<br />\n";
                if (count($this->lastErrorFields) > 0) {
                    $output .= "=============<br />\n";
                    $output .= "Bind Variables:<br />\n";
                    foreach ($this->lastErrorFields as $k => $l) {
                        if (is_array($l)) {
                            $output .= "$k => (" . implode(', ', $l) . ")<br />\n";
                        } else {
                            $output .= "$k => $l<br />\n";
                        }
                    }
                }
                break;
        }

        $this->clearError();
        return $output;
    }
}
