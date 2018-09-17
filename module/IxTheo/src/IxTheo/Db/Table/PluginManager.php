<?php

namespace IxTheo\Db\Table;

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
        $this->aliases['IxTheoUser']                        = 'IxTheo\Db\Table\IxTheoUser';
        $this->aliases['pdasubscription']                   = 'IxTheo\Db\Table\PDASubscription';
        $this->aliases['subscription']                      = 'IxTheo\Db\Table\Subscription';

        $this->factories['IxTheo\Db\Table\IxTheoUser']      = 'VuFind\Db\Table\GatewayFactory';
        $this->factories['IxTheo\Db\Table\PDASubscription'] = 'VuFind\Db\Table\GatewayFactory';
        $this->factories['IxTheo\Db\Table\Subscription']    = 'VuFind\Db\Table\GatewayFactory';

        $this->addAbstractFactory('IxTheo\Db\Table\PluginFactory');
        return parent::__construct($configOrContainerInstance, $v3config);
    }
}
