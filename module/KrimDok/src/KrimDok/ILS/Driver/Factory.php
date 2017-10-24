<?php

namespace KrimDok\ILS\Driver;


use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\ILS\Driver\Factory
{
    public static function getKrimDokILS(ServiceManager $sm)
    {
        return new KrimDokILS(
            $sm->getServiceLocator()->get('VuFind\RecordLoader'),
            $sm->getServiceLocator()->get('VuFind\Search')
        );
    }

}
