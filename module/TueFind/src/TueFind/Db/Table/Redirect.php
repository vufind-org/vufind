<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;

class Redirect extends \TueFind\Db\Table\Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_redirect'
    ) {
        // if $rowObj is null this might cause a type hinting problem,
        // already asked demiankatz if he can fix this
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Insert an URL with an optional group.
     * Timestamp will be added automatically, for later statistical analysis.
     *
     * @param string $url   The redirect target
     * @param string $group A group which might be use for later statistics
     */
    public function insertUrl(string $url, string $group=null) {
        $this->insert(['url' => $url, 'group_name' => $group]);
    }
}
