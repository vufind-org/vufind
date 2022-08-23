<?php
namespace TueFind\Mailer;

use Laminas\Mail\Address;
use Laminas\Mail\AddressList;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use VuFind\Exception\Mail as MailException;

class Mailer extends \VuFind\Mailer\Mailer {

    protected $container;

    protected $config;

    public function __construct(\Laminas\Mail\Transport\TransportInterface $transport, \Interop\Container\ContainerInterface $container)
    {
        parent::__construct($transport);
        $this->container = $container;
        $this->config = $container->get('VuFind\Config')->get('config');
    }

    /**
     * Send an email message, append custom footer to body
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string|MimeMessage         $body    Message body
     * @param string                     $cc      CC recipient (null for none)
     * @param string|Address|AddressList $replyTo Reply-To address (or delimited
     * list, null for none)
     * @param bool                       $enableSpamfilter  TueFind: Add header
     * to use anti spam. Postfix must be configured accordingly.
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body, $cc = null, $replyTo = null, $enableSpamfilter = false)
    {
        // TueFind-specific modifications
        $email = $this->config->Site->email;
        $email_from = $this->config->Site->email_from;

        if ($email != null) {
            $footer = $this->translate('mail_footer_please_contact') . PHP_EOL . $email;
            if ($body instanceof MimeMessage) {
                $part = new MimePart();
                $part->setType(\Laminas\Mime\Mime::TYPE_TEXT);
                $part->setContent($footer);
                $body->addPart($part);
            } else {
                $body .= PHP_EOL . '--' . PHP_EOL . $footer;
            }
        }

        if ($email_from != null) {
            $from = $email_from;
        }

        // Original VuFind-Code starts here
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
            // Add the original from address as the reply-to address unless
            // a reply-to address has been specified
            if (count($replyTo) === 0) {
                $replyTo->add($fromEmail);
            }
            if (!($from instanceof Address)) {
                $from = new Address($from);
            }
            $name = $from->getName();
            if (!$name) {
                [$fromPre] = explode('@', $from->getEmail());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($this->fromAddressOverride, $name);
        }

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $body instanceof MimeMessage
                ? $this->getNewBlankMessage()
                : $this->getNewMessage();
            $message->addFrom($from)
                ->addTo($recipients)
                ->setBody($body)
                ->setSubject($subject);
            if ($cc !== null) {
                $message->addCc($cc);
            }
            if ($replyTo) {
                $message->addReplyTo($replyTo);
            }

            // TueFind: Add header for spamfilter
            if ($enableSpamfilter) {
                $headers = $message->getHeaders();
                $headers->addHeaderLine('X-TueFind-Spamfilter', 'enabled');
            }

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

    public function getDefaultLinkSubject()
    {
        return $this->translate('bulk_email_title', ['%%siteTitle%%' => $this->config->Site->title]);
    }

    public function getDefaultRecordSubject($record)
    {
        return $this->translate('Library Catalog Record', [ '%%siteTitle%%' => $this->config->Site->title ]) . ': '
            . $record->getBreadcrumb();
    }
}
