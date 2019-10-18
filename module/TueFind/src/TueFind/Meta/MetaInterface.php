<?php

namespace TueFind\Meta;

interface MetaInterface {
    public function addMetatags(\VuFind\RecordDriver\DefaultRecord $driver);
}
