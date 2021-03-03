<?php

namespace TueFind\Db\Table;

use VuFind\Db\Table\GatewayFactory;

class PluginManager extends \VuFind\Db\Table\PluginManager {

    use \TueFind\PluginManagerExtensionTrait;

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
        $this->addOverride('aliases', 'redirect', Redirect::class);
        $this->addOverride('aliases', 'rss_feed', RssFeed::class);
        $this->addOverride('aliases', 'user', User::class);
        $this->addOverride('factories', Redirect::class, GatewayFactory::class);
        $this->addOverride('factories', RssFeed::class, GatewayFactory::class);
        $this->addOverride('factories', User::class, \VuFind\Db\Table\UserFactory::class);
        $this->applyOverrides();

        $this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
