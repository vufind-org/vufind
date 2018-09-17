<?php

namespace IxTheo\Db\Row;

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
        $this->aliases['IxTheoUser']                        = 'IxTheo\Db\Row\IxTheoUser';
        $this->aliases['pdasubscription']                   = 'IxTheo\Db\Row\PDASubscription';
        $this->aliases['subscription']                      = 'IxTheo\Db\Row\Subscription';

        $this->factories['IxTheo\Db\Row\IxTheoUser']      = 'VuFind\Db\Row\RowGatewayFactory';
        $this->factories['IxTheo\Db\Row\PDASubscription']   = 'VuFind\Db\Row\RowGatewayFactory';
        $this->factories['IxTheo\Db\Row\Subscription']      = 'VuFind\Db\Row\RowGatewayFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
