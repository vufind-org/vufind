<?php

namespace KrimDok\ILS\Driver;

class PluginManager extends \VuFind\ILS\Driver\PluginManager {

    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['KrimDokILS'] = 'KrimDok\ILS\Driver\KrimDokILS';
        $this->factories['KrimDok\ILS\Driver\KrimDokILS'] = 'KrimDok\ILS\Driver\KrimDokILSFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
