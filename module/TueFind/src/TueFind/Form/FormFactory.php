<?php

namespace TueFind\Form;

use Interop\Container\ContainerInterface;

class FormFactory extends \VuFind\Form\FormFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config')->toArray();
        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $viewHelperManager = $container->get('ViewHelperManager');

        return new $requestedName(
            // TueFind: Also pass Site config
            $yamlReader, $viewHelperManager, $config['Feedback'] ?? null, $config['Site'] ?? null
        );
    }
}
