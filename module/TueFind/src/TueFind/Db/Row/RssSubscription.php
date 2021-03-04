<?php

namespace TueFind\Db\Row;

class RssSubscription extends \VuFind\Db\Row\RowGateway
{
    public function __construct(\Laminas\Db\Adapter\Adapter $adapter)
    {
        parent::__construct('id', 'tuefind_rss_subscriptions', $adapter);
    }
}
