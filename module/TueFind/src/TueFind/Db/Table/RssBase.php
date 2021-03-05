<?php

namespace TueFind\Db\Table;

class RssBase extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    protected $instance;

    public function setInstance($instance)
    {
        $this->instance = $instance;
    }
}
