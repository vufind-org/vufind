<?php

namespace VuFind\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;

class DatabaseHandler extends AbstractProcessingHandler
{
    use VerbosityTrait;
}
