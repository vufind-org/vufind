<?php
namespace VZG\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'recorddriver' => array(
                'factories' => array(
                    'solrdefault' => 'VZG\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'VZG\RecordDriver\Factory::getSolrMarc',
                ),
            ),
            'ils_driver' => array(
                'invokables' => array(
                    'paia' => 'VZG\ILS\Driver\PAIA',
                ),
            ),
        ),
    ),
);

return $config;
