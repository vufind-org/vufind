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
    protected $rowClass = null;
    
    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

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
     * Initialize
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return;
        }
        $this->adapter = $this->getServiceLocator()->getServiceLocator()
            ->get('DBAdapter');
        parent::initialize();
        if (null !== $this->rowClass) {
            $resultSetPrototype = $this->getResultSetPrototype();
            $prototype = new $this->rowClass($this->getAdapter());
            if ($prototype instanceof ServiceLocatorAwareInterface) {
                $prototype->setServiceLocator($this->getServiceLocator());
            }
            $resultSetPrototype->setArrayObjectPrototype($prototype);
        }
    }

    /**
     * Create a new row.
     *
     * @return object
     */
    public function createRow()
    {
        return clone($this->getResultSetPrototype()->getArrayObjectPrototype());
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

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Gateway
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}
