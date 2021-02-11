<?php

namespace TueFind\Db\Row;

class Redirect extends \VuFind\Db\Row\RowGateway
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct(['url', 'timestamp'], 'tuefind_redirect', $adapter);
    }
}
