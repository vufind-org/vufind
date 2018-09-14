<?php

namespace TueFind;

use \Zend\Mvc\MvcEvent;

/**
 * Bot protection
 *
 * If an input field with a special name contains contents, then it must be filled out by a bot.
 * The field exists in the HTML form, but is hidden by CSS.
 */
class BotProtect {

    const BOT_PROTECT_FIELD = 'botprotect';

    static public function CheckRequest(MvcEvent $event) {
        if ($event->getRequest()->getQuery(self::BOT_PROTECT_FIELD) != '') {
            $response = $event->getResponse();
            $response->setStatusCode(400);
            $response->setContent("bot detected");
            $response->send();
            exit;
        }
    }
}
