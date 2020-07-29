<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use Zend\Db\Adapter\Adapter;

class Gateway extends \VuFind\Db\Table\Gateway
{

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    // This file only exists to solve a type hinting problem.
    // The old VuFind code does not allow null values in RowGateway.
    // Demiankatz was informed and wants to fix this probably in VuFind 8.
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        ?RowGateway $rowObj, $table
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
}
