<?php
namespace TueFind\Mailer;

use VuFind\Exception\Mail as MailException;
use Interop\Container\ContainerInterface;
use Laminas\Mail\Address;
use Laminas\Mail\AddressList;

class Mailer extends \VuFind\Mailer\Mailer {

    protected $container;

    public function __construct(\Laminas\Mail\Transport\TransportInterface $transport, ContainerInterface $container)
    {
        parent::__construct($transport);
        $this->container = $container;
    }

    /**
     * Send an email message, append custom footer to body
     *
     * @param string|Address|AddressList $to                Recipient email address (or
     * delimited list)
     * @param string|Address             $from              Sender name and email address
     * @param string                     $subject           Subject line for message
     * @param string                     $body              Message body
     * @param string                     $cc                CC recipient (null for none)
     * @param bool                       $enableSpamfilter  TueFind: Add header to use anti spam. Postfix must be configured accordingly.
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body, $cc = null, $reply_to = null, $enableSpamfilter = false)
    {
        $config = $this->container->get('VuFind\Config')->get('config');
        $email = $config->Site->email;
        $email_from = $config->Site->email_from;

        if ($email != null) {
            $footer = $this->translate('mail_footer_please_contact') . PHP_EOL . $email;
            $body .= PHP_EOL . '--' . PHP_EOL . $footer;
        }

        if ($email_from != null) {
            $from = $email_from;
        }

        $message = $this->assembleMail($to, $from, $subject, $body, $cc, $reply_to);

        // TueFind: Add header for spamfilter
        if ($enableSpamfilter) {
            $headers = $message->getHeaders();
            $headers->addHeaderLine('X-TueFind-Spamfilter', 'enabled');
        }

        try {
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }

    /**
     * Translate a string for use in Mail client
     * @see VuFind\Controller\AbstractBase->translate
     */
    public function translate($msg, $tokens = [], $default = null) {
        return $this->container->get('ViewRenderer')->plugin('translate')->__invoke($msg, $tokens, $default);
    }

    /*
     * Assemble the complete message but do not yet send it
     * @return The Mail message
     */
    function assembleMail($to, $from, $subject, $body, $cc = null, $replyTo = null) {
        $recipients = $this->convertToAddressList($to);
        $replyTo = $this->convertToAddressList($replyTo);

        // Validate email addresses:
        if ($this->maxRecipients > 0) {
            if ($this->maxRecipients < count($recipients)) {
                throw new MailException('Too Many Email Recipients');
            }
        }
        $validator = new \Laminas\Validator\EmailAddress();
        if (count($recipients) == 0) {
            throw new MailException('Invalid Recipient Email Address');
        }
        foreach ($recipients as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException('Invalid Recipient Email Address');
            }
        }
        foreach ($replyTo as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException('Invalid Reply-To Email Address');
            }
        }
        $fromEmail = ($from instanceof Address)
            ? $from->getEmail() : $from;
        if (!$validator->isValid($fromEmail)) {
            throw new MailException('Invalid Sender Email Address');
        }

        if (!empty($this->fromAddressOverride)
            && $this->fromAddressOverride != $fromEmail
        ) {
            $replyTo->add($fromEmail);
            if (!($from instanceof Address)) {
                $from = new Address($from);
            }
            $name = $from->getName();
            if (!$name) {
                list($fromPre) = explode('@', $from->getEmail());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($this->fromAddressOverride, $name);
        }

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Assemble message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($recipients)
                ->setBody($body)
                ->setSubject($subject);
            if ($cc !== null) {
                $message->addCc($cc);
            }
            if ($replyTo) {
                $message->addReplyTo($replyTo);
            }
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
        return $message;
    }

    public function getDefaultLinkSubject()
    {
        $config = $this->container->get('VuFind\Config')->get('config');
        return $this->translate('bulk_email_title', ['%%siteTitle%%' => $config->Site->title]);
    }

    public function getDefaultRecordSubject($record)
    {
        $config = $this->container->get('VuFind\Config')->get('config');

        return $this->translate('Library Catalog Record', [ '%%siteTitle%%' => $config->Site->title ]) . ': '
            . $record->getBreadcrumb();
    }
}
