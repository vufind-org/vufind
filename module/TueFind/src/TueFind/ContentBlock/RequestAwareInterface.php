<?php

namespace TueFind\ContentBlock;

interface RequestAwareInterface {
    public function setRequest(\Zend\Http\Request $request);
}
