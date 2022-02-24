<?php
namespace TueFindApi\Controller;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiControllerFactory extends \VuFindApi\Controller\ApiControllerFactory {
     public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
     ) {
        if (!empty($options)) {
             throw new \Exception('Unexpected options sent to factory.');
        }
        $controller =  parent::__invoke($container, $requestedName, $options);
        $controller->addApi($container->get('ControllerManager')->get('MltApi'));
        return $controller;
    }
}
?>

