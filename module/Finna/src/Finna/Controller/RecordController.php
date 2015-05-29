<?php
/**
 * Record Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;
use VuFind\Search\Memory, Zend\Mail as Mail;

/**
 * Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends \VuFind\Controller\RecordController
{
    use RecordControllerTrait;

    /**
     * Create record feedback form and send feedback to correct recipient.
     *
     * @return mixed
     * @throws \Exception
     */
    public function feedbackAction()
    {
        $view = $this->createViewModel();

        if ($this->formWasSubmitted('submitFeedback')) {
            $flashMsg = $this->flashMessenger();

            $message = $this->params()->fromPost('feedback_message');
            $senderEmail = $this->params()->fromPost('from');
            $validator = new \Zend\Validator\EmailAddress();
            if (!$validator->isValid($senderEmail)) {
                $flashMsg->setNamespace('error')
                    ->addMessage('Email address is invalid');
                return $view;
            }

            $driver = $this->loadRecord();
            $dataSource = $driver->getDataSource();
            $dataSources = $this->getServiceLocator()->get('VuFind\Config')
                ->get('datasources');

            $inst = isset($dataSources->$dataSource) ?
                $dataSources->$dataSource : null;
            $recipientEmail = isset($inst->feedbackEmail) ?
                $inst->feedbackEmail : null;
            if ($recipientEmail == null) {
                throw new \Exception(
                    'Feedback Module Error:'
                    . 'Recipient Email Unset (see datasources.ini)'
                );
            }

            $emailSubject = $this->translate(
                'feedback_on_record',
                ['%%record%%' => $driver->getBreadcrumb()]
            );
            $serverUrl = $this->getRequest()->getServer('REQUEST_SCHEME');
            $serverUrl .= '://' . $this->getRequest()->getServer('HTTP_HOST');

            $emailMessage = "\n" . $this->translate('This email was sent from');
            $emailMessage .= ": " . $senderEmail . "\n";
            $emailMessage .=
                "------------------------------------------------------------\n";
            $emailMessage .= $this->getViewRenderer()->partial(
                'RecordDriver/SolrDefault/result-email.phtml',
                [
                    'driver' => $driver,
                    'info' => ['baseUrl' => $serverUrl],
                ]
            );
            $emailMessage .=
                "------------------------------------------------------------\n";
            if (!empty($message)) {
                $emailMessage .= "\n" . $this
                    ->translate('Message From Sender') . ":\n";
                $emailMessage .= "\n" . $message . "\n\n";
            }

            // This sets up the email to be sent
            $mail = new Mail\Message();
            $mail->setEncoding('UTF-8');
            $mail->setBody($emailMessage);
            $mail->setFrom($senderEmail);
            $mail->addTo($recipientEmail);
            $mail->setSubject($emailSubject);
            $headers = $mail->getHeaders();
            $headers->removeHeader('Content-Type');
            $headers->addHeaderLine('Content-Type', 'text/plain; charset=UTF-8');

            $this->getServiceLocator()->get('VuFind\Mailer')->getTransport()
                ->send($mail);

            if (!$this->inLightbox()) {
                $flashMsg->setNamespace('info')
                    ->addMessage('Thank you for your feedback');
                $this->redirectToRecord('');
            }
        }

        return $view;
    }

}
