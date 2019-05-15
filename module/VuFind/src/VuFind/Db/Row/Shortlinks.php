<?php

namespace VuFind\Db\Row;

class Shortlinks extends RowGateway
{
    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'shortlinks', $adapter);
    }
}
