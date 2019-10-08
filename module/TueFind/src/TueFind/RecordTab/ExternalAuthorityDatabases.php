<?php

namespace TueFind\RecordTab;

class ExternalAuthorityDatabases extends \VuFind\RecordTab\AbstractBase
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->accessPermission = null;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'External Databases';
    }
}
