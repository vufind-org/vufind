<?php

$app = include __DIR__ . '/application.php';
return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet(
    $app->getServiceManager()->get('doctrine.entity_manager.orm_default')
);
