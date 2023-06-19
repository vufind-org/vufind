<?php

namespace VuFind\Search\Factory;

class EPFBackendFactory extends EdsBackendFactory
{

    protected function getServiceName()
    {
        return 'EPF';
    }

    protected function createConnectorOptions()
    {
        $options = parent::createConnectorOptions();

        if (isset($this->edsConfig->General->session_url)) {
            $options['session_url'] = $this->edsConfig->General->session_url;
        }

        return $options;
    }

}