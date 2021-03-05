<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;

class RssFeed extends RssBase
{
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_rss_feeds'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getFeedsSortedByName()
    {
        $select = $this->getSql()->select();
        $select->where->like('subsystem_types', '%' . $this->instance . '%');
        $select->order('feed_name ASC');
        return $this->selectWith($select);
    }
}
