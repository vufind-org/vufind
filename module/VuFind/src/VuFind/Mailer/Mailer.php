<?php
/**
 * VuFind Mailer Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Mailer;
use VuFind\Exception\Mail as MailException,
    Zend\Mail\Message,
    Zend\Mail\Header\ContentType;

/**
 * VuFind Mailer Class
 *
 * @category VuFind2
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Mailer implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    /**
     * Mail transport
     *
     * @var \Zend\Mail\Transport\TransportInterface
     */
    protected $transport;

    /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * Constructor
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     */
    public function __construct(\Zend\Mail\Transport\TransportInterface $transport)
    {
        $this->setTransport($transport);
    }

    /**
     * Translate a string if a translator is provided.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    public function translate($msg)
    {
        return (null !== $this->translator)
            ? $this->translator->translate($msg) : $msg;
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return Mailer
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Get the mail transport object.
     *
     * @return \Zend\Mail\Transport\TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * Get a blank email message object.
     *
     * @return Message
     */
    public function getNewMessage()
    {
        $message = new Message();
        $message->setEncoding('UTF-8');
        $headers = $message->getHeaders();
        $ctype = new ContentType();
        $ctype->setType('text/plain');
        $ctype->addParameter('charset', 'UTF-8');
        $headers->addHeader($ctype);
        return $message;
    }

    /**
     * Set the mail transport object.
     *
     * @param \Zend\Mail\Transport\TransportInterface $transport Mail transport
     * object
     *
     * @return void
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
    }

    /**
     * Send an email message.
     *
     * @param string $to      Recipient email address
     * @param string $from    Sender email address
     * @param string $subject Subject line for message
     * @param string $body    Message body
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body)
    {
        // Validate sender and recipient
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($to)) {
            throw new MailException('Invalid Recipient Email Address');
        }
        if (!$validator->isValid($from)) {
            throw new MailException('Invalid Sender Email Address');
        }

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($to)
                ->setBody($body)
                ->setSubject($subject);
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string                          $to      Recipient email address
     * @param string                          $from    Sender email address
     * @param string                          $msg     User notes to include in
     * message
     * @param string                          $url     URL to share
     * @param \Zend\View\Renderer\PhpRenderer $view    View object (used to render
     * email templates)
     * @param string                          $subject Subject for email (optional)
     *
     * @throws MailException
     * @return void
     */
    public function sendLink($to, $from, $msg, $url, $view, $subject = null)
    {
        if (is_null($subject)) {
            $subject = 'Library Catalog Search Result';
        }
        $subject = $this->translate($subject);
        $body = $view->partial(
            'Email/share-link.phtml',
            array(
                'msgUrl' => $url, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }

    /**
     * Send an email message representing a record.
     *
     * @param string                            $to     Recipient email address
     * @param string                            $from   Sender email address
     * @param string                            $msg    User notes to include in
     * message
     * @param \VuFind\RecordDriver\AbstractBase $record Record being emailed
     * @param \Zend\View\Renderer\PhpRenderer   $view   View object (used to render
     * email templates)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecord($to, $from, $msg, $record, $view)
    {
        $subject = $this->translate('Library Catalog Record') . ': '
            . $record->getBreadcrumb();
        $body = $view->partial(
            'Email/record.phtml',
            array(
                'driver' => $record, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }
}