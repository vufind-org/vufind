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


use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\DigitalContent\OverdriveConnector;

/**
 * Overdrive Controller supports actions for Overdrive Integration
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

    /**
     * Overdrive Connector
     *
     * @var OverdriveConnector $connector Overdrive Connector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->setLogger($sm->get('VuFind\Logger'));
        $this->connector = $sm->get('VuFind\DigitalContent\OverdriveConnector');
        parent::__construct($sm);
        $this->debug("ODRC constructed");
    }

    /**
     * My Content Action
     * Prepares the view for the Overdrive MyContent template.
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     */
    public function mycontentAction()
    {
        $this->debug("ODC mycontent action");
        //force login
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $holds = [];
        $checkouts = [];
        $checkoutsUnavailable = false;
        $holdsUnavailable = false;

        //check on this patrons's access to Overdrive
        $odAccessResult = $this->connector->getAccess();

        if (!$odAccessResult->status) {
            $this->debug("result:".print_r($odAccessResult, true));
            $this->flashMessenger()->addErrorMessage(
                $this->translate(
                    $odAccessResult->code,
                    ["%%message%%" => $odAccessResult->msg]
                )
            );
            $checkoutsUnavailable = true;
            $holdsUnavailable = true;
        }

        if ($odAccessResult->status) {
            //get the current Overdrive checkouts
            //for this user and add to our array of IDS
            $checkoutResults = $this->connector->getCheckouts(true);
            if (!$checkoutResults->status) {
                $this->flashMessenger()->addMessage(
                    $checkoutResults->code, 'error'
                );
                $checkoutsUnavailable = true;
            } else {
                foreach ($checkoutResults->data as $checkout) {
                  $mycheckout = [];
                  $mycheckout['checkout'] = $checkout;
                  
                  if($checkout->metadata->mediaType=="Magazine"){
                    
                    $mycheckout['checkout']->isMagazine=true;
                    $this->debug("loading magazine metadata for {$checkout->reserveId}");
                    $idToLoad = strtolower($checkout->metadata->parentMagazineReferenceId);
                    $this->debug("loading magazine parent with id: $idToLoad instead");
                  } else{
                    $mycheckout['checkout']->isMagazine=false;
                    $idToLoad = strtolower($checkout->reserveId);
                  }
                  
                  try {
                    $this->debug("loading checkout using: $idToLoad");
                    $mycheckout['record']
                        = $this->serviceLocator->get('VuFind\Record\Loader')
                            ->load($idToLoad );
                    $checkouts[] = $mycheckout;
                  }catch (\VuFind\Exception\RecordMissing $e){
                        $this->debug("missing record in index: $idToLoad");
                        //checkout is missing from Solr
                        $this->flashMessenger()->addMessage(
                            "One or more checkouts could not be displayed properly: ".
                            $e->getMessage(), 'error'
                        );
                        //get metadata from overdrive.
                        $meta = $this->connector->getMetadata([strtolower($checkout->reserveId)]);
                        $mycheckout['metadata'] = $meta[strtolower($checkout->reserveId)];
                        $checkouts[] = $mycheckout; 
                  }
                }
            }
            //get the current Overdrive holds for this user and add to
            // our array of IDS
            $holdsResults = $this->connector->getHolds(true);
            if (!$holdsResults->status) {
                if ($checkoutResults->status) {
                    $this->flashMessenger()->addMessage(
                        $holdsResults->code, 'error'
                    );
                }
                $holdsUnavailable = true;
            } else {
                foreach ($holdsResults->data as $hold) {
                    $myhold['hold'] = $hold;
                    try {
                        $this->debug("loading hold");
                        $myhold['record']
                            = $this->serviceLocator->get('VuFind\Record\Loader')
                            ->load(strtolower($hold->reserveId));
                        $this->debug("loaded hold");
                        $holds[] = $myhold;
                    }catch (\VuFind\Exception\RecordMissing $e){
                        //hold is missing from Solr
                        $this->flashMessenger()->addMessage(
                            "One or more holds could not be displayed properly: ".
                            $e->getMessage(), 'error'
                        );
                        
                        //get metadata from overdrive.
                        $meta = $this->connector->getMetadata([$hold->reserveId]);
                        $myhold['metadata'] = $meta[$hold->reserveId];
                        $holds[] = $myhold;
                    }
                }
            }
        }
        //Future: get reading history will be here
        //Future: get hold and checkoutlimit using the Patron Info API

        $view = $this->createViewModel(
            compact(
                'checkoutsUnavailable', 'holdsUnavailable',
                'checkouts', 'holds'
            )
        );

        $view->setTemplate('myresearch/odmycontent');
        return $view;
    }

    /**
     * Get Status Action
     * Supports the ajax getStatus calls
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     */
    public function getStatusAction()
    {
        $this->debug("ODC getStatus action");
        $ids = $this->params()->fromPost(
            'id', $this->params()->fromQuery('id', [])
        );
        $this->debug("ODRC availability for :" . print_r($ids, true));
        $result = $this->connector->getAvailabilityBulk($ids);
        $view = $this->createViewModel(compact('ids', 'result'));
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
     * @return array|bool|\Laminas\View\Model\ViewModel
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
        $this->debug("patron: " . print_r($patron, true));

        $od_id = $this->params()->fromQuery('od_id');
        $rec_id = $this->params()->fromQuery('rec_id');
        $action = $this->params()->fromQuery('action');
        $edition = $this->params()->fromPost('edition',false);
        $isMagazine = false;
        $holdEmail = "";

        //place hold action comes in through the form
        if (null !== $this->params()->fromPost('doAction')) {
            $action = $this->params()->fromPost('doAction');
        }

        //place hold action comes in through the form
        if (null !== $this->params()->fromPost('getTitleFormat')) {
            $format = $this->params()->fromPost('getTitleFormat');
        }

        $format = $this->params()->fromQuery('getTitleFormat');

        $this->debug("ODRC od_id=$od_id rec_id=$rec_id action=$action");
        //load the Record Driver.  Should be a SolrOverdrive  driver.
        $driver = $this->serviceLocator->get('VuFind\Record\Loader')->load(
            $rec_id
        );

        $formats = $driver->getDigitalFormats();
        $title = $driver->getTitle();
        $cover = $driver->getThumbnail('small');
        $listAuthors = $driver->getPrimaryAuthors();
        $issues = [];
        if (!$action) {
            //double check the availability in case it
            //has changed since the page was loaded.
            $avail = $driver->getOverdriveAvailability();
            if ($avail->copiesAvailable > 0) {
                $action = "checkoutConfirm";
            } else {
                $action = "holdConfirm";
            }
        }
        //CONFIRM SECTION
        if ($action == "checkoutConfirm") {
            //looks like this is a magazine...
            if(current($formats)->id == "magazine-overdrive"){
                $isMagazine = true;
                $result = $this->connector->getMagazineIssues($od_id);
                if($result->status){
                    $issues = $result->data->products;
                }else{
                    $this->debug("couldn't get issues for checkout");
                    $result->status = false;
                    $result->code = "OD_CODE_NOMAGISSUES";
                    $result->msg = "No magazine issue available.";
                }
            }else{
                $result = $this->connector->getResultObject();
                //check to make sure they don't already have this checked out
                //shouldn't need to refresh.
                if ($checkout = $this->connector->getCheckout($od_id, false)) {
                    $result->status = false;
                    $result->data->checkout = $checkout;
                    $result->code = "OD_CODE_ALREADY_CHECKED_OUT";
                } elseif ($hold = $this->connector->getHold($od_id, false)) {
                    if($hold->holdReadyForCheckout){
                        $this->debug("hold is avail for checkout: $od_id");
                        $result->status = true;
                    }else {
                        $result->status = false;
                        $result->data->hold = $hold;
                        $result->code = "OD_CODE_ALREADY_ON_HOLD";
                    }
                } else {
                    $result->status = true;
                }
            }
            $actionTitleCode = "od_checkout";
        } elseif ($action == "holdConfirm") {
            $result = $this->connector->getResultObject();
            //check to make sure they don't already have this checked out
            //shouldn't need to refresh.
            if ($checkout = $this->connector->getCheckout($od_id, false)) {
                $result->status = false;
                $result->data->checkout = $checkout;
                $result->code = "OD_CODE_ALREADY_CHECKED_OUT";
                $this->debug("title already checked out: $od_id");
            } elseif ($hold = $this->connector->getHold($od_id, false)) {
                $result->status = false;
                $result->data->hold = $hold;
                $result->code = "OD_CODE_ALREADY_ON_HOLD";
                $this->debug("title already on hold: $od_id");
            } else {
                $result->status = true;
            }
            $actionTitleCode = "od_hold";
        } elseif ($action == "cancelHoldConfirm") {
            $actionTitleCode = "od_cancel_hold";
        } elseif ($action == "suspHoldConfirm") {
            $actionTitleCode = "od_susp_hold";
        } elseif ($action == "editHoldConfirm") {
            $actionTitleCode = "od_susp_hold_edit";
        } elseif ($action == "editHoldEmailConfirm") {
            $actionTitleCode = "od_edit_hold_email";
            $hold = $this->connector->getHold($od_id, false);
            $holdEmail = $hold->emailAddress;

        } elseif ($action == "returnTitleConfirm") {
            $actionTitleCode = "od_early_return";
/*
        } elseif ($action == "getTitleConfirm") {
            //get only formats that are available...
            $formats = $driver->getAvailableDigitalFormats();
            $actionTitleCode = "od_get_title";
*/
        //ACTION SECTION
        } elseif ($action == "doCheckout") {
            $actionTitleCode = "od_checkout";
            if($edition){
              $od_id = $edition;
              $this->debug("checking out edition: $edition");
            }
            $result = $this->connector->doOverdriveCheckout($od_id);

        } elseif ($action == "placeHold") {
            $actionTitleCode = "od_hold";
            $email = $this->params()->fromPost('email');
            $result = $this->connector->placeOverDriveHold($od_id, $email);
            if($result->status){
                $result->code = "od_hold_place_success";
                $result->codeParams = ["%%holdListPosition%%" => $result->data->holdListPosition];
            }else{
                $result->code = "od_hold_place_failure";
            }

        } elseif ($action == "editHoldEmail") {
            $actionTitleCode = "od_hold";
            $email = $this->params()->fromPost('email');
            $result = $this->connector->updateOverDriveHold($od_id, $email);

            if($result->status){
                $result->code = "od_hold_update_success";
            }else{
                $result->code = "od_hold_update_failure";
            }

        } elseif ($action == "suspendHold") {
            $actionTitleCode = "od_susp_hold";
            $suspendValue = $this->params()->fromPost("suspendValue");
            $hold = $this->connector->getHold($od_id, false);
            $holdEmail = $hold->emailAddress;
            $suspensionType = $suspendValue==-1?"indefinite":"limited";
            $result = $this->connector->suspendHold($od_id, $holdEmail, $suspensionType, $suspendValue);

            if($result->status){
                $result->code = "od_hold_redelivery";
                $result->codeParams = ["%%days%%" => $result->data->holdSuspension->numberOfDays];
            }else{
                $result->code = "od_hold_update_failure";
            }
        } elseif ($action == "editSuspendedHold") {
            $actionTitleCode = "od_susp_hold_edit";
            $suspendValue = $this->params()->fromPost("suspendValue");
            if($suspendValue==0){
                $result = $this->connector->deleteHoldSuspension($od_id);
            }else{
                $hold = $this->connector->getHold($od_id, false);
                $holdEmail = $hold->emailAddress;
                $suspensionType = $suspendValue==-1?"indefinite":"limited";
            
                $result = $this->connector->editSuspendedHold($od_id, $holdEmail, $suspensionType, $suspendValue);
            }    
            if($result->status){
                $result->code = "od_hold_update_success";
            }else{
                $result->code = "od_hold_update_failure";
            }
            

        } elseif ($action == "cancelHold") {
            $actionTitleCode = "od_cancel_hold";
            $result = $this->connector->cancelHold($od_id);
            if($result->status){
                $result->code = "od_hold_cancel_success";
            }else{
                $result->code = "od_hold_cancel_failure";
            }

        } elseif ($action == "returnTitle") {
            $actionTitleCode = "od_early_return";
            $result = $this->connector->returnResource($od_id);
            if($result->status){
                $result->code = "od_return_success";
            }else{
                $result->code = "od_return_failure";
            }

        } elseif ($action == "getTitle") {
            $actionTitleCode = "od_get_title";
            $this->debug(
                "Get Title action. Getting downloadredirect"
            );
            $result = $this->connector->getDownloadRedirect($od_id);
            if ($result->status) {
                $this->debug("DL redir: ".$result->data->downloadRedirect);
            }else{
                $this->debug("result: ".print_r($result,true));
                $result->code = "od_gettitle_failure";
            }
        } else {
            $this->logWarning("overdrive action not defined: $action");
        }

        $view = $this->createViewModel(
            compact(
                'od_id', 'rec_id', 'action',
                'result', 'formats', 'cover', 'title', 'actionTitleCode',
                'listAuthors','holdEmail','issues','isMagazine'
            )
        );

        $view->setTemplate('RecordDriver/SolrOverdrive/hold');
        return $view;
    }
}
