<?php
namespace IxTheo\Controller\Plugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PDASubscriptionsFactory implements FactoryInterface {

   public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
       return new PDASubscriptions($container->get('VuFind\DbTablePluginManager'),
                                   $container->get('VuFind\Mailer'),
                                   $container->get('VuFind\RecordLoader'),
                                   $container->get('VuFind\Config'),
                                   $container->get('ViewRenderer'));

   }

}
