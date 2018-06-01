<?php
/**
 * Overdrive Controller
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Controller
 * @author   Brent Palmer <brent-palmer@ipcl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
 namespace VuFind\Controller;

use Zend\ServiceManager\ServiceLocatorInterface;
 use Zend\Log\LoggerAwareInterface;

 /**
  * Overdrive Controller supports action for Overdrive Integration
  *
  * @category VuFind
  * @package  Controller
  * @author   Brent Palmer <brent-palmer@ipcl.org>
  * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
  * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
  */
class OverdriveController extends AbstractBase implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    
    protected $connector;
    
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->setLogger($sm->get('VuFind\Logger'));
        $this->searchClassId = 'Overdrive';
        $this->connector = $sm->get('VuFind\DigitalContent\OverdriveConnector');
        parent::__construct($sm);
        $this->debug("ODRC constructed");
    }
    
  
    /**
     * My Content Action
     * Prepares the view for the Overdrive MyContent template.
     *
     * @return array|bool|\Zend\View\Model\ViewModel
     */
    public function mycontentAction()
    {
        $this->debug("ODC mycontent action");
        
        //force login
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        
        $overdriveIDs = array();
        //get the current Overdrive checkouts
        //for this user and add to our array of IDS
        $checkouts = $this->connector->getCheckouts($patron, true);

        
        //get the current Overdrive holds for this user and add to our array of IDS
        $holds = $this->connector->getHolds($patron, true);

        $mycheckouts = array();
        
        foreach ($checkouts->data as $checkout) {
            $mycheckout['checkout'] = $checkout;
            $mycheckout['record']
                = $this->serviceLocator->get('VuFind\Record\Loader')
                    ->load(strtolower($checkout->reserveId));
            $mycheckouts[] = $mycheckout;
        }
        
        $myholds = array();
        foreach ($holds->data as $hold) {
            $myhold['hold'] = $hold;
            $myhold['record']
                = $this->serviceLocator->get('VuFind\Record\Loader')
                    ->load(strtolower($hold->reserveId));
            $myholds[] = $myhold;
        }
        $this->debug("view model");
        $view = $this->createViewModel(
            [
              'checkouts' => $mycheckouts,
              'holds' => $myholds,
            ]
        );

        $view->setTemplate('myresearch/odmycontent');
        return $view;
    }
  
    /**
     * Get Status Action
     * Supports the ajax getStatus calls
     *
     * @return array|bool|\Zend\View\Model\ViewModel
     */
    public function getStatusAction()
    {
        $this->debug("ODC getStatus action");

        $ids =  $this->params(
        )->fromPost('id', $this->params()->fromQuery('id', []));
        
        $this->debug("ODRC availability for :".print_r($ids, true));
        $result = $this->connector->getAvailabilityBulk($ids);
        
        $view = $this->createViewModel(
            [
              'ids' => $ids,
              'action' => $action,
              'result' => $result,
            ]
        );

        $view->setTemplate('RecordDriver/SolrOverdrive/status-full');
        $this->layout()->setTemplate('layout/lightbox');
        return $view;
    }

    /**
     * Hold Action
     *
     * Hold Action handles all of the actions involving
     * Overdrive content including checkout, hold, cancel hold etc.
     *
     * @return array|bool|\Zend\View\Model\ViewModel
     * @todo   Deal with situation that an unlogged in user requests
     *     an action but the action is no longer valid since they
     *     already have the content on hold/checked out or do not have acceess
     */
    public function holdAction()
    {
        $this->debug("ODC Hold action");
        
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $this->debug("patron: ".print_r($patron, true));
        //TODO Check patron eligibility
        //$driver->checkPatronAccess();
        
        $od_id = $this->params()->fromQuery('od_id');
        $rec_id = $this->params()->fromQuery('rec_id');
        $action = $this->params()->fromQuery('action');
        
        //place hold action comes in through the form
        if (null !== $this->params()->fromPost('placeHold')) {
            $action = "placeHold";
        }
        
        $this->debug("ODRC od_id=$od_id rec_id=$rec_id action=$action");
        //load the Record Driver.  Should be a SolrOverdrive  driver.
        $driver = $this->serviceLocator->get('VuFind\Record\Loader')->load($rec_id);
        $formats = $driver->getDigitalFormats();
        $title = $driver->getTitle();
        $cover = $driver->getThumbnail('small');
        
        if (!$action) {
            //double check the availability in case it
            //has changed since the page was loaded.
            $avail = $driver->getOverdriveAvailability();
            //if($avail->copiesOwned >0){
            if ($avail->copiesAvailable>0) {
                $action = "checkout";
            } else {
                $action = "hold";
            }
            //}else{
                //create some kind of notification to user that something went wrong
            //}
        } elseif ($action=="returnTitleConfirm") {
            $this->debug("confirming return title");
        } elseif ($action=="returnTitle") {
            $this->debug("returning title using OD connector");
            $result = $this->connector->returnResource($od_id);
        } elseif ($action=="placeHold") {
            $email = $this->params()->fromPost('email');
            $this->debug("placing Hold through OD now using email: $email");
            $result = $this->connector->placeOverDriveHold($od_id, $patron, $email);
        } elseif ($action=="cancelHold") {
            $this->debug("canceling Hold through OD now");
            $result = $this->connector->cancelHold($od_id, $patron);
        } elseif ($action=="doCheckout") {
            $this->debug("doing Checkout through OD now");
            $result = $this->connector->doOverdriveCheckout($od_id, $patron);
        } else {
            $this->debug("action not defined: $action");
        }
        
        $view = $this->createViewModel(
            [
                'od_id' => $od_id,
                'rec_id' => $rec_id,
                'action' => $action,
                'result' => $result,
                'formats' => $formats,
                'cover' => $cover,
                'title' => $title
            ]
        );

        $view->setTemplate('RecordDriver/SolrOverdrive/hold');
        return $view;
    }
}
