<?php

namespace TueFind\Mailer;

use Interop\Container\ContainerInterface;

class Factory extends \VuFind\Mailer\Factory {

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        // Load configurations:
        $config = $container->get('VuFind\Config\PluginManager')->get('config');

        // Create service:
        $class = new $requestedName($this->getTransport($config), $container);
        if (!empty($config->Mail->override_from)) {
            $class->setFromAddressOverride($config->Mail->override_from);
        }
        return $class;
    }
}
