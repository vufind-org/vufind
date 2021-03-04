<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;

class RssItem extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_rss_items'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getItemsSortedByPubDate($instance)
    {
        $select = $this->getSql()->select();
        $select->join('tuefind_rss_feeds', 'tuefind_rss_items.rss_feeds_id = tuefind_rss_feeds.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->where->like('tuefind_rss_feeds.subsystem_types', '%' . $instance . '%');
        $select->order('pub_date DESC');
        return $this->selectWith($select);
    }

    public function getItemsForUserSortedByPubDate($userId) {
        $select = $this->getSql()->select();
        $select->join('tuefind_rss_feeds', 'tuefind_rss_items.rss_feeds_id = tuefind_rss_feeds.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->join('tuefind_rss_subscriptions', 'tuefind_rss_items.rss_feeds_id = tuefind_rss_subscriptions.rss_feeds_id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->where('tuefind_rss_subscriptions.user_id', $userId);
        $select->order('pub_date DESC');
        return $this->selectWith($select);
    }
}
