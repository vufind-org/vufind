<?php

namespace IxTheo\Service;

use \Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Service\Factory  {

    /**
     * Construct the export helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Export
     */
    public static function getExport(ServiceManager $sm)
    {
        return new \IxTheo\Export(
            $sm->get('\VuFind\Config')->get('config'),
            $sm->get('\VuFind\Config')->get('export')
        );
    }
}
?>
