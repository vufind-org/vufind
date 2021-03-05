<?php

namespace TueFind\Db\Table;

use Interop\Container\ContainerInterface;

class RssFactory extends \VuFind\Db\Table\GatewayFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $table = parent::__invoke($container, $requestedName, $options);
        $instance = $container->get('ViewHelperManager')->get('tuefind')->getTueFindInstance();
        $table->setInstance($instance);
        return $table;
    }
}
