<?php

namespace TueFind\Db\Row;

class Publication extends \VuFind\Db\Row\RowGateway
{
    public function __construct($adapter)
    {
        parent::__construct('id', 'tuefind_publications', $adapter);
    }
}
