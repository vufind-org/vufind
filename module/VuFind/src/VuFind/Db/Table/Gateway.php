<?php
/**
 * Generic VuFind table gateway.
 *
 * PHP version 7
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\TableGateway\Feature;

/**
 * Generic VuFind table gateway.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Gateway extends AbstractTableGateway
{
    /**
     * Table manager
     *
     * @var PluginManager
     */
    protected $tableManager;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj, $table
    ) {
        $this->adapter = $adapter;
        $this->tableManager = $tm;
        $this->table = $table;

        $this->initializeFeatures($cfg);
        $this->initialize();

        if (null !== $rowObj) {
            $resultSetPrototype = $this->getResultSetPrototype();
            $resultSetPrototype->setArrayObjectPrototype($rowObj);
        }
    }

    /**
     * Initialize features
     *
     * @param array $cfg Zend Framework configuration
     *
     * @return void
     */
    public function initializeFeatures($cfg)
    {
        // Special case for PostgreSQL sequences:
        if ($this->adapter->getDriver()->getDatabasePlatformName() == "Postgresql") {
            $maps = $cfg['vufind']['pgsql_seq_mapping'] ?? null;
            if (isset($maps[$this->table])) {
                if (!is_object($this->featureSet)) {
                    $this->featureSet = new Feature\FeatureSet();
                }
                $this->featureSet->addFeature(
                    new Feature\SequenceFeature(
                        $maps[$this->table][0], $maps[$this->table][1]
                    )
                );
            }
        }
    }

    /**
     * Create a new row.
     *
     * @return object
     */
    public function createRow()
    {
        $obj = clone $this->getResultSetPrototype()->getArrayObjectPrototype();

        // If this is a PostgreSQL connection, we may need to initialize the ID
        // from a sequence:
        if ($this->adapter
            && $this->adapter->getDriver()->getDatabasePlatformName() == "Postgresql"
            && $obj instanceof \VuFind\Db\Row\RowGateway
        ) {
            // Do we have a sequence feature?
            $feature = $this->featureSet->getFeatureByClassName(
                'Zend\Db\TableGateway\Feature\SequenceFeature'
            );
            if ($feature) {
                $key = $obj->getPrimaryKeyColumn();
                if (count($key) != 1) {
                    throw new \Exception('Unexpected number of key columns.');
                }
                $col = $key[0];
                $obj->$col = $feature->nextSequenceId();
            }
        }

        return $obj;
    }

    /**
     * Get access to another table.
     *
     * @param string $table Table name
     *
     * @return Gateway
     */
    public function getDbTable($table)
    {
        return $this->tableManager->get($table);
    }
}
