<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\NodeJS\Server\ZombieServer;
use Behat\Mink\Driver\ZombieDriver;

class ZombieConfig extends AbstractConfig
{
    public static function getInstance()
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function createDriver()
    {
        $nodeBinary = isset($_SERVER['NODE_BIN']) ? $_SERVER['NODE_BIN'] : 'node';
        $server = new ZombieServer('127.0.0.1', 8124, $nodeBinary);

        if (isset($_SERVER['NODE_MODULES_PATH'])) {
            $server->setNodeModulesPath($_SERVER['NODE_MODULES_PATH']);
        }

        return new ZombieDriver($server);
    }

    /**
     * {@inheritdoc}
     */
    public function skipMessage($testCase, $test)
    {
        if (
            'Behat\Mink\Tests\Driver\Form\Html5Test' === $testCase
            && in_array($test, array(
                'testHtml5FormInputAttribute',
                'testHtml5FormButtonAttribute',
                'testHtml5FormOutside',
                'testHtml5FormRadioAttribute',
            ))
        ) {
            return 'Zombie.js doesn\'t HTML5 form attributes. See https://github.com/assaf/zombie/issues/635';
        }

        if (
            'Behat\Mink\Tests\Driver\Js\JavascriptEvaluationTest' === $testCase
            && 'testWait' === $test
        ) {
            return 'Zombie automatically waits for events to fire, so the wait test is irrelevant';
        }

        if ('Behat\Mink\Tests\Driver\Js\ChangeEventTest' === $testCase && 'testIssue178' === $test) {
            return 'Zombie does not trigger the keyup event when writing a value in a text input to simulate keyboard';
        }


        return parent::skipMessage($testCase, $test);
    }
}
