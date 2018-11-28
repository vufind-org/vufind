<?php

namespace TueFind\Mailer;

class Factory extends \VuFind\Mailer\Factory {

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return mixed
     */
    public function createService(\Zend\ServiceManager\ServiceLocatorInterface $sm)
    {
        // Load configurations:
        $config = $sm->get('VuFind\Config')->get('config');

        // Create service:
        return new Mailer($this->getTransport($config), $sm);
    }
}
