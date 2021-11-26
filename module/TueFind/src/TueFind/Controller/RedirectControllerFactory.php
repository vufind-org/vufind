<?php
namespace TueFind\Controller;

use Interop\Container\ContainerInterface;

class RedirectControllerFactory extends AbstractBaseWithDbTableFactory {

   public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
       $controller = parent::__invoke($container, $requestedName, $options);
       $controller->setDecoder($container->get('ViewHelperManager')->get('tuefind'));
       $controller->setKflService($container->get(\TueFind\Service\KfL::class));
       return $controller;
   }

}
