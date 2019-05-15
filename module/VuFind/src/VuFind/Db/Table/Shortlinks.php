<?php

namespace VuFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use Zend\Db\Adapter\Adapter;

class Shortlinks extends Gateway
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
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'shortlinks'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }
}
