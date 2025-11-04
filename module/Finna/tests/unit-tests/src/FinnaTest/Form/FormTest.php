<?php

/**
 * Form test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Helper;

use Finna\Config\YamlReader;
use Finna\Form\Form;
use Generator;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Form\Handler\PluginManager;
use VuFindTest\Feature\FixtureTrait;

/**
 * Form test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FormTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Get testFormFieldValues data
     *
     * @return Generator
     */
    public static function getTestFormFieldValuesData(): Generator
    {
        $patron = [
            'id' => 'test.11122',
            'cat_username' => 'test.111233',
            '__local_id' => '11122',
            '__local_cat_username' => '111233',
            'firstname' => 'Tane',
            'lastname' => 'Tene',
            'email' => 'patronemail@email.fi',
            '__source' => 'test',
        ];
        yield 'Form has preferPatronInformation true' => [
            'prefer_patron_information.json',
            [
                'setContactInformation' => [
                    'f1' => null,
                    'f2' => null,
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'submitButton' => null,
                ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                ],
            ],
            $patron,
        ];
        yield 'Form has preferPatronInformation false' => [
            'default.json',
            [
                'setContactInformation' => [
                    'f1' => null,
                    'f2' => null,
                    'name' => 'Test Tester',
                    'email' => 'dbemail@email.fi',
                    'submitButton' => null,
                ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Test Tester',
                    'email' => 'dbemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                ],
            ],
            $patron,
        ];
        yield 'Form has includeBarcode true' => [
            'include_barcode.json',
            [
                'setContactInformation' =>
                    [
                        'f1' => null,
                        'f2' => null,
                        'name' => 'Tane Tene',
                        'email' => 'patronemail@email.fi',
                        'submitButton' => null,
                    ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                    'userCatUsername' => '111233',
                ],
            ],
            $patron,
        ];
        yield 'Form has includePatronId true' => [
            'include_patron_id.json',
            [
                'setContactInformation' =>
                    [
                        'f1' => null,
                        'f2' => null,
                        'name' => 'Tane Tene',
                        'email' => 'patronemail@email.fi',
                        'submitButton' => null,
                    ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                    'userCatId' => '11122',
                ],
            ],
            $patron,
        ];
        $patron['cat_username'] = $patron['__local_cat_username'];
        unset($patron['__local_cat_username']);
        $patron['id'] = $patron['__local_id'];
        unset($patron['__local_id']);
        unset($patron['__source']);
        yield 'Patron has no __local_ or __source keys' => [
            'prefer_patron_information.json',
            [
                'setContactInformation' => [
                    'f1' => null,
                    'f2' => null,
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'submitButton' => null,
                ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                ],
            ],
            $patron,
        ];
        yield 'Patron has no __local_ or __source keys and form has includeBarcode true' => [
            'include_barcode.json',
            [
                'setContactInformation' =>
                    [
                        'f1' => null,
                        'f2' => null,
                        'name' => 'Tane Tene',
                        'email' => 'patronemail@email.fi',
                        'submitButton' => null,
                    ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                    'userCatUsername' => '111233',
                ],
            ],
            $patron,
        ];
        yield 'Patron has no __local_ or __source keys and form has includePatronId true' => [
            'include_patron_id.json',
            [
                'setContactInformation' =>
                    [
                        'f1' => null,
                        'f2' => null,
                        'name' => 'Tane Tene',
                        'email' => 'patronemail@email.fi',
                        'submitButton' => null,
                    ],
                'mapRequestParamsToFieldValues' => [
                    'f1' => 'value1',
                    'f2' => 'Test',
                    'name' => 'Tane Tene',
                    'email' => 'patronemail@email.fi',
                    'userLoginMethod' => 'login_method_',
                    'userRoles' => '',
                    'userCatId' => '11122',
                ],
            ],
            $patron,
        ];
    }

    /**
     * Get form.
     *
     * @param array $formConfig Form configuration
     *
     * @return MockObject&Form
     */
    public function getForm(array $formConfig): MockObject&Form
    {
        $yamlReader = $this->createMock(YamlReader::class);
        $yamlReader->expects($this->any())->method('getFinna')->willReturn($formConfig);
        $form = $this->getMockBuilder(Form::class)->setConstructorArgs([
            $yamlReader,
            $this->createMock(HelperPluginManager::class),
            $this->createMock(PluginManager::class),
            [],
        ])->onlyMethods([])->getMock();

        return $form;
    }

    /**
     * Get UserEntityInterface mock
     *
     * @param ?int    $getIdReturn        getId return value
     * @param ?string $getFirstnameReturn getFirstname return value
     * @param ?string $getLastnameReturn  getLastname return value
     * @param ?string $getEmailReturn     getEmail return value
     *
     * @return MockObject&\VuFind\Db\Entity\UserEntityInterface
     */
    public function getUserEntityInterfaceMock(
        ?int $getIdReturn = null,
        ?string $getFirstnameReturn = null,
        ?string $getLastnameReturn = null,
        ?string $getEmailReturn = null,
    ): MockObject&\VuFind\Db\Entity\UserEntityInterface {
        $mockUserEntityInterface = $this->createMock(\VuFind\Db\Entity\UserEntityInterface::class);
        if (isset($getIdReturn)) {
            $mockUserEntityInterface->expects($this->any())->method('getId')->willReturn($getIdReturn);
        }
        if (isset($getFirstnameReturn)) {
            $mockUserEntityInterface->expects($this->any())->method('getFirstname')->willReturn($getFirstnameReturn);
        }
        if (isset($getLastnameReturn)) {
            $mockUserEntityInterface->expects($this->any())->method('getLastname')->willReturn($getLastnameReturn);
        }
        if (isset($getEmailReturn)) {
            $mockUserEntityInterface->expects($this->any())->method('getEmail')->willReturn($getEmailReturn);
        }
        return $mockUserEntityInterface;
    }

    /**
     * Test setting user information to form
     *
     * @param string $fixture  Fixture name
     * @param array  $expected Expected results
     * @param array  $patron   User patron.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestFormFieldValuesData')]
    public function testFormFieldValues(string $fixture, array $expected, array $patron): void
    {
        $formConfig = $this->getJsonFixture("form/$fixture", 'Finna');
        $user = $this->getUserEntityInterfaceMock(1, 'Test', 'Tester', 'dbemail@email.fi');

        $form = $this->getForm($formConfig);
        $form->setUser($user, [], $patron);
        $form->setFormId('test');
        $form->setContactInformation();
        $form->isValid();
        $this->assertEquals($expected['setContactInformation'], $form->getData());

        // Test setting data
        $values = [
            'name' => $form->getData()['name'],
            'email' => $form->getData()['email'],
            'f1' => 'o1',
            'f2' => 'Test',
        ];
        $mapped = $form->mapRequestParamsToFieldValues($values);
        $result = [];
        foreach ($mapped as $field) {
            $result[$field['name']] = $field['value'];
        }
        $this->assertEquals($expected['mapRequestParamsToFieldValues'], $result);
    }
}
