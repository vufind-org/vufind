<?php

namespace IxTheo\Controller\Plugin;
use VuFind\Exception\LoginRequired as LoginRequiredException,
    Zend\Mvc\Controller\Plugin\AbstractPlugin,
    VuFind\Db\Row\User, VuFind\Record\Cache,
    Zend\Mail\Address,
    Zend\Mail\AddressList;



/**
 * Zend action helper to perform favorites-related actions
 */
class PDASubscriptions extends AbstractPlugin
{

    protected $pm;

    public function __construct(\Zend\Mvc\Controller\PluginManager $pm) {
        $this->pm = $pm;
    }


    /**
     * Delete a group of pda-subscriptions.
     *
     * @param array $ids    Array of IDs in source|id format.
     * @param mixed $listID ID of list to delete from (null for all
     * lists)
     * @param User  $user   Logged in user
     *
     * @return void
     */
    public function delete($ids, $listID, $user)
    {
        // Sort $ids into useful array:
        $sorted = [];
        foreach ($ids as $current) {
            list($source, $id) = explode('|', $current, 2);
            if (!isset($sorted[$source])) {
                $sorted[$source] = [];
            }
            $sorted[$source][] = $id;
        }

        // Delete PDA entries one source at a time, using a different object depending
        // on whether we are working with a list or the table.
        if (empty($listID)) {
            foreach ($sorted as $source => $ids) {
                $user->removeResourcesById($ids, $source);
           }
        } else {
            $table = $this->getController()->getTable('UserList');
            $list = $table->getExisting($listID);
            foreach ($sorted as $source => $ids) {
                $list->removeResourcesById($user, $ids, $source);
            }
        }
    }

    function getUserData($userId) {
       $userTable = $this->pm->getServiceLocator()->get('Vufind\DbTablePluginManager')->get('User');
       $select = $userTable->getSql()->select()->where(['id' => $userId]);

       $userRow = $userTable->selectWith($select)->current();
       $ixtheoUserTable = $this->pm->getServiceLocator()->get('Vufind\DbTablePluginManager')->get('IxTheoUser');
       $ixtheoSelect = $ixtheoUserTable->getSql()->select()->where(['id' => $userId]);
       $ixtheoUserRow = $ixtheoUserTable->selectWith($ixtheoSelect)->current();
       $userData = [ 'title' => $ixtheoUserRow->title != "Other" ? $ixtheoUserRow->title . " " : "",
                     'firstname' => $userRow->firstname,
                     'lastname' =>  $userRow->lastname,
                     'email' => $userRow->email,
                     'country' => $ixtheoUserRow->country,
                     'user_type' => $ixtheoUserRow->user_type ];
       return $userData;
    }

    function formatUserData($userData) {
       return [ ($userData['title'] != "" ? $userData['title'] . " " : "") . $userData['firstname'] . " " . $userData['lastname'],
                $userData['email'],
                $userData['country']
              ];
    }


    /*
     * Helper to handle one or several Addresses
     */

    function constructAddress($emailAddressString, $emailName = "") {
       $addresses = array_map('trim', explode(',', $emailAddressString));
       if (count($addresses) > 1) {
          $addressList = new AddressList;
          $addressList->addMany($addresses);
          return $addressList;
       }
       return new Address($emailAddressString, $emailName);
    }

    /*
     * Generic Mail send function
     */
    function sendEmail($recipientEmail, $recipientName, $senderEmail, $senderName, $emailSubject, $emailMessage) {
        try {
            $mailer = $this->pm->getServiceLocator()->get('VuFind\Mailer');
            $recipients = $this->constructAddress($recipientEmail, $recipientName);
            $mailer->setMaxRecipients(3);
            $mailer->send(
                 $recipients,
                 new Address($senderEmail, $senderName),
                 $emailSubject, $emailMessage
             );
        } catch (MailException $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'Error sending email');
        }
    }

    /*
     * Send notification to library
     */
    function sendPDANotificationEmail($post, $user, $data, $id) {
        $userDataRaw = $this->getUserData($user->id);
        $userType = $userDataRaw['user_type'];
        $userData = $this->formatUserData($userDataRaw);
        $senderData = $this->getPDASenderData($userType);
        $recipientData = $this->getPDAInstitutionRecipientData($userType);
        $emailSubject = "PDA Bestellung";
        $addressForDispatch = $post['addressfield'];
        $emailMessage = "Benutzer:\r\n" .  implode("\r\n", $userData) . "\r\n\r\n" .
                        "Versandaddresse:\r\n" . $addressForDispatch . "\r\n\r\n" .
                        "Titel:\r\n" . $this->getBookInformation($id) . "\r\n\r\n" .
                        "Link:\r\n" . $this->getRecordLink($id) . "\r\n\r\n" .
                        "Benutzer Typ:\r\n" . $userType;
        $this->sendEmail($recipientData['email'], $recipientData['name'], $senderData['email'], $senderData['name'], $emailSubject, $emailMessage);
    }


    function getRecordLink($id) {
        $url = $this->getController()->getServerUrl();
        // Strip our plugin part
        $url_parts = explode('/', $url);
        array_pop($url_parts);
        return implode('/', $url_parts);
    }


    function getBookInformation($id) {
        $recordLoader = $this->pm->getServiceLocator()->get('VuFind\RecordLoader');
        $driver = $recordLoader->load($id, 'Solr', false);
        $year = $driver->getPublicationDates()[0];
        $isbn = $driver->getISBNs()[0];
        return $driver->getAuthorsAsString() . ": " .
               $driver->getTitle() . " " .
               ($year != "" ? "(" . $year. ")" : "") . " " .
               ($isbn != "" ? "ISBN: " . $isbn : "");
    }

    /*
     * Get sender Mail addresses from site configuration
     * @param $realm category e.g. ixtheo, relbib
     */
    function getPDASenderData($realm) {
        $config = $this->pm->getServiceLocator()->get('VuFind\Config')->get('config');
        $site = isset($config->Site) ? $config->Site : null;
        $pda_sender = 'pda_sender_' . $realm;
        $pda_sender_name = 'pda_sender_name';
        $senderEmail = isset($site->$pda_sender) ? $site->$pda_sender : null;
        $senderName = isset($site->$pda_sender_name) ? $site->$pda_sender_name : null;
        return ['email' => $senderEmail, 'name' => $senderName ];
    }

    function getPDAInstitutionRecipientData($realm) {
        $config = $this->pm->getServiceLocator()->get('VuFind\Config')->get('config');
        $site = isset($config->Site) ? $config->Site : null;
        $pda_email = 'pda_email_' . $realm;
        $email = isset($site->$pda_email) ? $site->$pda_email : null;
        $name = "UB Tübingen PDA";
        return ['email' => $email, 'name' => $name];
    }

    function sendPDAUserNotificationEmail($post, $user, $data, $id) {
        $userDataRaw = $this->getUserData($user->id);
        $userType = $userDataRaw['user_type'];
        $userData = $this->formatUserData($userDataRaw);
        $senderData = $this->getPDASenderData($userType);
        $recipientEmail = $userData[1];
        $recipientName = $userData[0];
        $emailSubject = $this->controller->translate("Your PDA Order");
        $postalAddress = $this->controller->translate("You provided the following address") . ":\r\n" . $post['addressfield'] . "\r\n\r\n";
        $bookInformation = $this->controller->translate("Book Information") . ":\r\n" . $this->getBookInformation($id) . "\r\n\r\n";
        $opening = $this->controller->translate("Dear") . " " . $userData[0] . ",\r\n\r\n" .
                   $this->controller->translate("you triggered a PDA order") . ".\r\n";
        $renderer = $this->pm->getServiceLocator()->get('ViewRenderer');
        $infoText = $renderer->render($this->controller->forward()->dispatch('StaticPage', array(
            'action' => 'staticPage',
            'page' => 'PDASubscriptionMailInfoText'
        )));
        $emailMessage = $opening . $bookInformation . $postalAddress . $infoText . "\r\n\r\n" . $this->getPDAClosing($userType);
        $this->sendEmail($recipientEmail, $recipientName, $senderData['email'], $senderData['name'], $emailSubject, $emailMessage);
    }

    /*
     * Send unsubscribe notification to library
     */
    function sendPDAUnsubscribeEmail($user, $id) {
        $userDataRaw = $this->getUserData($user->id);
        $userType = $userDataRaw['user_type'];
        $userData = $this->formatUserData($userDataRaw);
        $senderData = $this->getPDASenderData($userType);
        $emailSubject = "PDA Abbestellung";
        $recipientData = $this->getPDAInstitutionRecipientData($userType);
        $recordLink = $this->getRecordLink($id);
        $emailMessage = "Abbestellung: " . $this->getBookInformation($id) . "\r\n\r\n" .
                        "Link: " . $recordLink . "\r\n\r\n" .
                        "für: " . $userData[0] . "(" . $userData[1] . ")" . " [Benutzertyp: " . $userType . "]";
        $this->sendEmail($recipientData['email'], $recipientData['name'], $senderData['email'], $senderData['name'], $emailSubject, $emailMessage);
    }

    /*
     * Send unsubscribe notification to user
     */
    function sendPDAUserUnsubscribeEmail($user, $id) {
        $userDataRaw = $this->getUserData($user->id);
        $userType = $userDataRaw['user_type'];
        $userData = $this->formatUserData($userDataRaw);
        $senderData = $this->getPDASenderData($userType);
        $emailSubject = $this->controller->translate("Cancellation of your PDA Order");
        $recipientName = $userData[0];
        $recipientEmail = $userData[1];
        $opening = $this->controller->translate("Dear") . " " . $userData[0] . ",\r\n\r\n" .
                   $this->controller->translate("you cancelled a PDA order") . ":\r\n";
        $emailMessage = $opening .  $this->getBookInformation($id) . "\r\n\r\n" . $this->getPDAClosing($userType);
        $this->sendEmail($recipientEmail, $recipientName, $senderData['email'], $senderData['name'], $emailSubject, $emailMessage);
    }

    function getPDAClosing($realm) {
        $salutation = ($realm === 'relbib') ? $this->controller->translate("Your Relbib Team") :
                      $this->controller->translate("Your IxTheo Team");
        return $this->controller->translate("Kind Regards") . "\r\n\r\n" . $salutation;
    }
}
