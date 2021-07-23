<?php

namespace TueFind\Controller;

class AjaxController extends \VuFind\Controller\AjaxController
{
   /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(\VuFind\AjaxHandler\PluginManager $am)
    {
        // Add notices to a key in the output
        set_error_handler(['TueFind\Controller\AjaxController', "storeError"]);
        parent::__construct($am);
    }
}
