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
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;
use Zend\Db\TableGateway\AbstractTableGateway,
    Zend\Db\TableGateway\Feature\FeatureSet,
    Zend\Db\TableGateway\Feature\GlobalAdapterFeature;

/**
 * Generic VuFind table gateway.
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Gateway extends AbstractTableGateway
{
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
        $this->featureSet = new FeatureSet();
        $this->featureSet->addFeature(new GlobalAdapterFeature());
        $this->initialize();
        if (!is_null($rowClass)) {
            $resultSetPrototype = $this->getResultSetPrototype();
            $resultSetPrototype
                ->setArrayObjectPrototype(new $rowClass($this->getAdapter()));
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
}
