<?php
namespace IxTheo\Mailer;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class Mailer extends \VuFind\Mailer\Mailer implements ServiceLocatorAwareInterface {

    use ServiceLocatorAwareTrait;

    /**
     * Send an email message, append custom footer to body
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string                     $body    Message body
     * @param string                     $cc      CC recipient (null for none)
     *
     * @throws MailException
     * @return void
     */
    public function send($to, $from, $subject, $body, $cc = null)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $email = $config->Site->email;
        $email_from = $config->Site->email_from;

        if ($email != null) {
            $footer = $this->translate('mail_footer_please_contact') . PHP_EOL . $email;
            $body .= PHP_EOL . '--' . PHP_EOL . $footer;
        }

        if ($email_from != null) {
            $from = $email_from;
        }

        parent::send($to, $from, $subject, $body, $cc);
    }

    /**
     * Translate a string for use in Mail client
     * @see VuFind\Controller\AbstractBase->translate
     */
    public function translate($msg, $tokens = [], $default = null) {
        return $this->getServiceLocator()->get('viewmanager')->getRenderer()->plugin('translate')->__invoke($msg, $tokens, $default);
    }
}
