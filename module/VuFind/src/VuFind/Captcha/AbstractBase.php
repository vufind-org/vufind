<?php

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

abstract class AbstractBase {
    
    public function getJsIncludes(): array {
        return [];
    }
    
    abstract public function getHtml(): string;
    
    abstract public function verify(Params $params): bool;
}
