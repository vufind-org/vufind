<?php

namespace KrimDok\ILS\Driver;


use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\ILS\Driver\Factory
{
    public static function getKrimDok(ServiceManager $sm)
    {
        return new KrimDok(
            $sm->getServiceLocator()->get('VuFind\RecordLoader'),
            $sm->getServiceLocator()->get('VuFind\Search')
        );
    }

}