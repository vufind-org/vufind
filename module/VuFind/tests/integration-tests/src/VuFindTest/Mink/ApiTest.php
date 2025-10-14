<?php

/**
 * Mink test class for the VuFind APIs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use VuFind\Db\Entity\ApiKeyEntityInterface;
use VuFind\Db\Service\ApiKeyServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\DeveloperSettings\DeveloperSettingsStatus;

use function strlen;

/**
 * Mink test class for the VuFind APIs.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ApiTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;
    use \VuFindTest\Feature\UserCreationTrait;
    use \VuFindTest\Feature\DemoDriverTestTrait;
    use \VuFindTest\Feature\EmailTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Helper function to create a new API key entity
     *
     * @param string $title API key title
     *
     * @return ApiKeyEntityInterface
     */
    protected function getApiKey(string $title = 'test_title'): ApiKeyEntityInterface
    {
        $userService = $this->getLiveDbServiceManager()->get(UserServiceInterface::class);
        $user = $userService->getUserByUsername('username1');
        $developerSettingsService = $this->getLiveDatabaseContainer()->get(DeveloperSettingsService::class);
        $apiKey = $developerSettingsService->generateApiKeyForUser($user, $title);
        return $apiKey;
    }

    /**
     * Helper function to get a revoked API key entity
     *
     * @return ApiKeyEntityInterface
     */
    protected function getRevokedApiKey(): ApiKeyEntityInterface
    {
        $apiKey = $this->getApiKey('fail_title');
        $apiKey->setRevoked(true);
        $apiKeyService = $this->getLiveDbServiceManager()->get(ApiKeyServiceInterface::class);
        $apiKeyService->persistEntity($apiKey);
        return $apiKey;
    }

    /**
     * Helper function to set correct API key configs
     *
     * @param string $mode API key mode
     *
     * @return void
     */
    protected function setApiKeyConfigs(string $mode = 'disabled'): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'API_Keys' => [
                        'mode' => $mode,
                        'token_salt' => 'test_token_salt',
                        'key_limit' => 10,
                    ],
                    'Mail' => [
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                        'message_log_format' => $this->getEmailLogFormat(),
                    ],
                ],
                'permissions' => [
                    'default.Developer' => [
                        'permission' => 'feature.Developer',
                        'role' => 'loggedin',
                        'assertion' => 'VerifiedEmail',
                    ],
                    'enable-record-api' => [
                        'permission' => 'access.api.Record',
                        'require' => 'ANY',
                        'role' => 'guest',
                    ],
                ],
            ]
        );
    }

    /**
     * Make a record retrieval API call and return the resulting page object.
     *
     * @param string  $id          Record    ID to retrieve.
     * @param ?string $apiKeyToken API key token.
     *
     * @return Element
     */
    protected function makeRecordApiCall($id = 'testbug2', ?string $apiKeyToken = null): Element
    {
        $session = $this->getMinkSession();
        if ($apiKeyToken) {
            $session->setApiKeyToken($apiKeyToken);
        }
        $session->visit($this->getVuFindUrl() . '/api');
        $page = $session->getPage();
        $this->clickCss($page, '#operations-Record-get_record button');
        $this->clickCss($page, '#operations-Record-get_record .try-out button');
        $this->findCssAndSetValue($page, '#operations-Record-get_record input[type="text"]', $id);
        $this->clickCss($page, '#operations-Record-get_record .execute-wrapper button');
        return $page;
    }

    /**
     * Test that the API is disabled by default.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testApiDisabledByDefault(): void
    {
        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '403',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
    }

    /**
     * Test that the API can be turned on and accessed via Swagger UI.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testEnabledRecordApi(): void
    {
        $this->changeConfigs(
            [
                'permissions' => [
                    'enable-record-api' => [
                        'permission' => 'access.api.Record',
                        'require' => 'ANY',
                        'role' => 'guest',
                    ],
                ],
            ]
        );
        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
    }

    /**
     * Test generating API keys
     *
     * @return void
     */
    public function testApiKeys(): void
    {
        $this->setApiKeyConfigs(DeveloperSettingsStatus::OPTIONAL->value);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->createAndLoginUser($page);

        // Go to profile page:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->waitForPageLoad($page);

        $this->findAndAssertLink($page, 'Email Verification');
        $this->clickCss($page, '.alert-info div a');
        $this->waitForPageLoad($page);

        // Extract the link from the provided message:
        $email = $this->getLoggedEmail();
        preg_match(
            '/You can verify your email address with this link: <(http.*)>/',
            $email->getBody()->getBody(),
            $matches
        );
        $verifyLink = $matches[1];

        // Follow the verification link:
        $session->visit($verifyLink);
        $this->waitForPageLoad($page);

        // Go to profile page:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->waitForPageLoad($page);

        // Now click the developer settings button:
        $this->findAndAssertLink($page, 'Developer settings')->click();
        $this->waitForPageLoad($page);

        // Now click the Generate new key button:
        $this->findAndAssertLink($page, 'Generate new key')->click();

        $this->findCssAndSetValue($page, '#api-key-title', 'test token');
        $this->clickCss($page, '.btn.btn-primary[name="submitButton"]');
        $text = $this->findCssAndGetText($page, '.alert-success');
        $testToken = trim(substr($text, strpos($text, ':') + 1));
        $this->assertStringStartsWith(
            'API key was generated successfully. Key will be displayed only once, so save it now:',
            $text
        );
        $this->assertTrue(strlen($testToken) > 0);

        $this->clickCss($page, '.btn-default[data-bs-dismiss="modal"]');
    }

    /**
     * Test API keys set to disabled.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testApiKeysDisabled(): void
    {
        $this->setApiKeyConfigs();

        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
    }

    /**
     * Test API keys set to Optional.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testApiKeysOptional(): void
    {
        $this->setApiKeyConfigs(DeveloperSettingsStatus::OPTIONAL->value);
        $apiKey = $this->getApiKey();

        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );

        $page = $this->makeRecordApiCall(apiKeyToken: $apiKey->getToken());
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
        $page = $this->makeRecordApiCall(apiKeyToken: 'failing_token');
        $this->assertEquals(
            '401',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
        $revokedKey = $this->getRevokedApiKey();
        $page = $this->makeRecordApiCall(apiKeyToken: $revokedKey->getToken());
        $this->assertEquals(
            '401',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );

        // Delete any created keys after tests are done
        $apiKeyService = $this->getLiveDatabaseContainer()->get(ApiKeyServiceInterface::class);
        $apiKeyService->deleteEntity($apiKey);
        $apiKeyService->deleteEntity($revokedKey);
    }

    /**
     * Test API keys set to enforced.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testApiKeysEnforced(): void
    {
        $this->setApiKeyConfigs(DeveloperSettingsStatus::ENFORCED->value);
        $apiKey = $this->getApiKey();

        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '401',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );

        $page = $this->makeRecordApiCall(apiKeyToken: $apiKey->getToken());
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
        $page = $this->makeRecordApiCall(apiKeyToken: 'failing_token');
        $this->assertEquals(
            '401',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
        $revokedKey = $this->getRevokedApiKey();
        $page = $this->makeRecordApiCall(apiKeyToken: $revokedKey->getToken());
        $this->assertEquals(
            '401',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );

        // Delete any created keys after tests are done
        $apiKeyService = $this->getLiveDatabaseContainer()->get(ApiKeyServiceInterface::class);
        $apiKeyService->deleteEntity($apiKey);
        $apiKeyService->deleteEntity($revokedKey);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
