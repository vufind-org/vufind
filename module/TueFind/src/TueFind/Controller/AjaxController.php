<?php
namespace TueFind\Controller;
use Zend\ServiceManager\ServiceLocatorInterface;

class AjaxController extends \VuFind\Controller\AjaxController
{

   /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        // Add notices to a key in the output
        set_error_handler(['TueFind\Controller\AjaxController', "storeError"]);
        parent::__construct($sm);
    }


   /**
     * Get Subscription Bundle Entries
     *
     * @return \Zend\Http\Response
     */
    protected function getSubscriptionBundleEntriesAjax()
    {
       $this->disableSessionWrites(); // Seems to be needed
       
       return $this->output('[ { "testvalue" : "TEST" }, { "testvalue2" : " TEST2 } ]', self::STATUS_OK);
    }
}
