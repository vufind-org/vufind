<?php

namespace IxTheo\Db\Table;

use VuFind\Db\Table\GatewayFactory;

class PluginManager extends \VuFind\Db\Table\PluginManager {
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
        $this->aliases['IxTheoUser']                        = IxTheoUser::class;
        $this->aliases['pdasubscription']                   = PDASubscription::class;
        $this->aliases['subscription']                      = Subscription::class;

        $this->factories['IxTheo\Db\Table\IxTheoUser']      = GatewayFactory::class;
        $this->factories['IxTheo\Db\Table\PDASubscription'] = GatewayFactory::class;
        $this->factories['IxTheo\Db\Table\Subscription']    = GatewayFactory::class;

        $this->addAbstractFactory(PluginFactory::class);
        return parent::__construct($configOrContainerInstance, $v3config);
    }
}
