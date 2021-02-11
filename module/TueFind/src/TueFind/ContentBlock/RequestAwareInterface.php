<?php

namespace TueFind\ContentBlock;

interface RequestAwareInterface {
    public function setRequest(\Laminas\Http\Request $request);
}
