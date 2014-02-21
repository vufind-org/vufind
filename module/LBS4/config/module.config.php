<?php
namespace LBS4\Module\Configuration;

$config = array(
    'vufind' => array(
        // This section contains service manager configurations for all VuFind
        // pluggable components:
        'plugin_managers' => array(
            'ils_driver' => array(
                'abstract_factories' => array('VuFind\ILS\Driver\PluginFactory'),
                'factories' => array(
                'lbs4' => function ($sm) {
                    return new \VuFind\ILS\Driver\LBS4(
                        $sm->getServiceLocator()->get('VuFind\RecordLoader')
                    );  
                },  
                ),
            ),
            'recorddriver' => array(
                'factories' => array(
                    'solropac' => function ($sm) {
                        $driver = new \LBS4\RecordDriver\SolrOpac(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        $driver->attachILS(
                        $sm->getServiceLocator()->get('VuFind\ILSConnection'),
                        $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
                        $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
                        );
                        return $driver;
                    },
                ),
            ),
        ),
    ),
);

return $config;
