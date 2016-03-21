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
 * @category VuFind
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
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends \VuFind\Controller\RecordController
{
    use RecordControllerTrait;
    use CatalogLoginTrait;

    /**
     * Create record feedback form and send feedback to correct recipient.
     *
     * @return \Zend\View\Model\ViewModel
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

            $flashMsg->addSuccessMessage('Thank you for your feedback');
            if ($this->getRequest()->getQuery('layout', 'no') !== 'lightbox'
                || 'layout/lightbox' != $this->layout()->getTemplate()
            ) {
                $this->redirectToRecord('');
            }
        }

        return $view;
    }

    /**
     * Load a normalized record from RecordManager for preview
     *
     * @param string $data   Record Metadata
     * @param string $format Metadata format
     * @param string $source Data source
     *
     * @return AbstractRecordDriver
     * @throw  \Exception
     */
    protected function loadPreviewRecord($data, $format, $source)
    {
        $config = $this->getConfig();
        if (empty($config->NormalizationPreview->url)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        $httpService = $this->serviceLocator->get('\VuFind\Http');
        $client = $httpService->createClient(
            $config->NormalizationPreview->url,
            \Zend\Http\Request::METHOD_POST
        );
        $client->setParameterPost(
            ['data' => $data, 'format' => $format, 'source' => $source]
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            throw new \Exception(
                'Failed to load preview: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase()
            );
        }
        $metadata = json_decode($response->getContent(), true);
        $recordFactory = $this->serviceLocator
            ->get('VuFind\RecordDriverPluginManager');
        $this->driver = $recordFactory->getSolrRecord($metadata);
        return $this->driver;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord()
    {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        // 0 = preview record
        if ($id != '0') {
            return parent::loadRecord();
        }
        $data = $this->params()->fromPost(
            'data', $this->params()->fromQuery('data', '')
        );
        $format = $this->params()->fromPost(
            'format', $this->params()->fromQuery('format', '')
        );
        $source = $this->params()->fromPost(
            'source', $this->params()->fromQuery('source', '')
        );
        if (!$data) {
            // Support marc parameter for Voyager compatibility
            $format = 'marc';
            if (!$source) {
                $source = '_marc_preview';
            }
            $data = $this->params()->fromPost(
                'marc', $this->params()->fromQuery('marc')
            );
            // For some strange reason recent Voyager versions double-encode the data
            // with encodeURIComponent
            if (substr($data, -3) == '%1D') {
                $data = urldecode($data);
            }
            // Voyager doesn't tell the proper encoding, so it's up to the browser to
            // decide. Try to handle both UTF-8 and ISO-8859-1.
            $len = substr($data, 0, 5);
            if (strlen($data) != $len) {
                $data = utf8_decode($data);
            }
        }
        if (!$data || !$format || !$source) {
            throw new \Exception('Missing parameters');
        }

        return $this->loadPreviewRecord($data, $format, $source);
    }
}
