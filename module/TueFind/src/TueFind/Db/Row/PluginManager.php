<?php

namespace TueFind\Db\Row;

use VuFind\Db\Row\RowGatewayFactory;

class PluginManager extends \VuFind\Db\Row\PluginManager {
    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['publication']            = Publication::class;
        $this->aliases['redirect']               = Redirect::class;
        $this->aliases['rss_feed']               = RssFeed::class;
        $this->aliases['rss_item']               = RssItem::class;
        $this->aliases['rss_subscription']       = RssSubscription::class;
        $this->aliases['user']                   = User::class;
        $this->aliases['user_authority']         = UserAuthority::class;
        $this->factories[Publication::class]     = RowGatewayFactory::class;
        $this->factories[Redirect::class]        = RowGatewayFactory::class;
        $this->factories[RssFeed::class]         = RowGatewayFactory::class;
        $this->factories[RssItem::class]         = RowGatewayFactory::class;
        $this->factories[RssSubscription::class] = RowGatewayFactory::class;
        $this->factories[User::class]            = \VuFind\Db\Row\UserFactory::class;
        $this->factories[UserAuthority::class]   = RowGatewayFactory::class;

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
