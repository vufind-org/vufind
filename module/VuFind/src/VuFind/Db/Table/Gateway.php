<?php
/**
 * Generic VuFind table gateway.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;
use Zend\Db\TableGateway\AbstractTableGateway,
    Zend\Db\TableGateway\Feature,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Generic VuFind table gateway.
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Gateway extends AbstractTableGateway implements ServiceLocatorAwareInterface
{
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    /**
     * Name of class used to represent rows (null for default)
     *
     * @var string
     */
    protected $rowClass = null;
    
    /**
     * Constructor
     *
     * @param string $table    Name of database table to interface with
     * @param string $rowClass Name of class used to represent rows (null for
     * default)
     */
    public function __construct($table, $rowClass = null)
    {
        $this->table = $table;
        $this->rowClass = $rowClass;
    }

    /**
     * Set database adapter
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     *
     * @return void
     */
    public function setAdapter(\Zend\Db\Adapter\Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Initialize
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return;
        }

        // Special case for PostgreSQL sequences:
        if ($this->adapter->getDriver()->getDatabasePlatformName() == "Postgresql") {
            $cfg = $this->getServiceLocator()->getServiceLocator()->get('config');
            $maps = isset($cfg['vufind']['pgsql_seq_mapping'])
                ? $cfg['vufind']['pgsql_seq_mapping'] : null;
            if (isset($maps[$this->table])) {
                $this->featureSet = new Feature\FeatureSet();
                $this->featureSet->addFeature(
                    new Feature\SequenceFeature(
                        $maps[$this->table][0], $maps[$this->table][1]
                    )
                );
            }
        }

        parent::initialize();
        if (null !== $this->rowClass) {
            $resultSetPrototype = $this->getResultSetPrototype();
            $resultSetPrototype->setArrayObjectPrototype(
                $this->initializeRowPrototype()
            );
        }
    }

    /**
     * Construct the prototype for rows.
     *
     * @return object
     */
    protected function initializeRowPrototype()
    {
        $prototype = new $this->rowClass($this->getAdapter());
        if ($prototype instanceof ServiceLocatorAwareInterface) {
            $prototype->setServiceLocator($this->getServiceLocator());
        }
        \VuFind\ServiceManager\Initializer::initInstance(
            $prototype, $this->getServiceLocator()->getServiceLocator()
        );
        return $prototype;
    }

    /**
     * Create a new row.
     *
     * @return object
     */
    public function createRow()
    {
        $obj = clone($this->getResultSetPrototype()->getArrayObjectPrototype());

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
        return $this->getServiceLocator()->get($table);
    }
}
