<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;

class RssSubscription extends RssBase
{
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_rss_subscriptions'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getSubscriptionsForUserSortedByName($userId)
    {
        $select = $this->getSql()->select();
        $select->join('tuefind_rss_feeds', 'tuefind_rss_subscriptions.rss_feeds_id = tuefind_rss_feeds.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->where(['user_id' => $userId]);
        $select->order('feed_name ASC');
        return $this->selectWith($select);
    }

    public function addSubscription($userId, $feedId)
    {
        $this->insert(['user_id' => $userId, 'rss_feeds_id' => $feedId]);
    }

    public function removeSubscription($userId, $feedId)
    {
        $delete = $this->getSql()->delete();
        $delete->where(['user_id' => $userId, 'rss_feeds_id' => $feedId]);
        $this->deleteWith($delete);
    }

}
