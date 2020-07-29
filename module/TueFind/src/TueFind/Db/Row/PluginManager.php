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
        $this->aliases['redirect']         = Redirects::class;
        $this->factories[Redirects::class] = RowGatewayFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
