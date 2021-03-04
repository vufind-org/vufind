<?php

namespace TueFind\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;

class RssSubscription extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'tuefind_rss_subscriptions'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function getSubscriptionsForUserSortedByName($user) {
        $select = $this->getSql()->select();
        $select->join('tuefind_rss_feeds', 'tuefind_rss_subscriptions.rss_feeds_id = tuefind_rss_feeds.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->where('user_id', $user->id);
        $select->order('feed_name ASC');
        return $this->selectWith($select);
    }

}
