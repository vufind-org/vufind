<?php

/**
 * Form Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Form;

use Symfony\Component\Yaml\Yaml;
use VuFind\Config\YamlReader;
use VuFind\Form\Form;

use function get_class;

/**
 * Form Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FormTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    protected $mockTestFormYamlReader = null;

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithoutConfiguration()
    {
        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class)
        );
        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->useCaptcha());
        $this->assertFalse($form->showOnlyForLoggedUsers());
        $this->assertEquals([], $form->getFormElementConfig());
        $this->assertEquals(
            [['email' => null, 'name' => null]],
            $form->getRecipient()
        );
        $this->assertNull($form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertEquals('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertEquals(
            'feedback_response',
            $form->getSubmitResponse()
        );
        $this->assertEquals([[], 'Email/form.phtml'], $form->formatEmailMessage([]));
        $this->assertEquals([], $form->mapRequestParamsToFieldValues([]));

        $this->assertEquals(
            'Laminas\InputFilter\InputFilter',
            get_class($form->getInputFilter())
        );
        $this->assertCount(0, $form->getSecondaryHandlers());
    }

    /**
     * Test defaults with defaults passed to constructor.
     *
     * @return void
     */
    public function testDefaultsWithConfiguration()
    {
        $defaults = [
            'recipient_email' => 'me@example.com',
            'recipient_name' => 'me',
            'email_subject' => 'subject',
        ];
        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class),
            ['Feedback' => $defaults]
        );
        $this->assertEquals(
            [['name' => 'me', 'email' => 'me@example.com']],
            $form->getRecipient()
        );
        $this->assertEquals('subject', $form->getEmailSubject([]));
    }

    /**
     * Test that the class blocks unknown form IDs.
     *
     * @return void
     */
    public function testUndefinedFormId()
    {
        $this->expectException(\VuFind\Exception\RecordMissing::class);
        $this->expectExceptionMessage('Form \'foo\' not found');

        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class)
        );
        $form->setFormId('foo');
    }

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithFormSet()
    {
        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class)
        );
        $form->setFormId('FeedbackSite');

        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->useCaptcha());
        $this->assertFalse($form->showOnlyForLoggedUsers());
        $this->assertEquals(
            [
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'required' => true,
                    'label' => 'Comments',
                    'settings' => ['rows' => 8],
                ],
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'feedback_name',
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );

        $this->assertEquals(
            [['email' => null, 'name' => null]],
            $form->getRecipient()
        );

        $this->assertEquals('feedback_title', $form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertEquals('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertEquals(
            'feedback_response',
            $form->getSubmitResponse()
        );
        $expectedFields = [
            [
                'type' => 'textarea',
                'value' => 'x',
                'valueLabel' => null,
                'label' => 'Comments',
                'name' => 'message',
                'required' => true,
                'settings' => ['rows' => 8],
            ],
            [
                'type' => 'text',
                'value' => 'y',
                'valueLabel' => null,
                'name' => 'name',
                'group' => '__sender__',
                'label' => 'feedback_name',
            ],
            [
                'type' => 'email',
                'value' => 'z@foo.com',
                'valueLabel' => null,
                'name' => 'email',
                'group' => '__sender__',
                'label' => 'feedback_email',
            ],
        ];
        $postParams = [
            'message' => 'x',
            'name' => 'y',
            'email' => 'z@foo.com',
        ];

        $this->assertEquals(
            [
                $expectedFields,
                'Email/form.phtml',
            ],
            $form->formatEmailMessage($postParams)
        );
        $this->assertEquals(
            $expectedFields,
            $form->mapRequestParamsToFieldValues($postParams)
        );
        $this->assertEquals(
            'Laminas\InputFilter\InputFilter',
            get_class($form->getInputFilter())
        );

        // Validators: Required field problems
        $form->setData(['email' => 'foo@bar.com', 'message' => null]);
        $this->assertFalse($form->isValid());
        $form->setData(['email' => 'foo@bar.com', 'message' => '']);
        $this->assertFalse($form->isValid());

        // Validators: Email problems
        $form->setData(['email' => ' ',  'message' => 'message']);
        $this->assertFalse($form->isValid());
        $form->setData(['email' => 'foo',  'message' => 'message']);
        $this->assertFalse($form->isValid());
        $form->setData(['email' => 'foo@', 'message' => 'message']);
        $this->assertFalse($form->isValid());
        $form->setData(['email' => 'foo@bar', 'message' => 'message']);
        $this->assertFalse($form->isValid());

        // Validators: Good data
        $form->setData(['email' => 'foo@bar.com', 'message' => 'message']);
        $this->assertTrue($form->isValid());
    }

    /**
     * Test sender field merging.
     *
     * @return void
     */
    public function testSenderFieldMerging()
    {
        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class)
        );
        $form->setFormId('FeedbackSite');

        $this->assertEquals(
            [
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'required' => true,
                    'label' => 'Comments',
                    'settings' => ['rows' => 8],
                ],
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'feedback_name',
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );

        $form = $this->getMockTestForm('TestSenderFields');
        $this->assertEquals(
            [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'Sender Name',
                    'settings' => [],
                ],
                [
                    'type' => 'text',
                    'name' => 'phone',
                    'group' => '__sender__',
                    'label' => 'Phone Number',
                    'settings' => [],
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                    'settings' => [],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'required' => true,
                    'label' => 'Comments',
                    'settings' => ['rows' => 8],
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );
    }

    /**
     * Test sender field merging.
     *
     * @return void
     */
    public function testSenderFieldMergingWithSettings()
    {
        $form = $this->getMockTestForm('TestSenderFieldsWithSettings');
        $this->assertEquals(
            [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'Sender Name',
                    'settings' => [
                        'size' => 100, // from feedbackforms/test.yaml
                    ],
                ],
                [
                    'type' => 'text',
                    'name' => 'phone',
                    'group' => '__sender__',
                    'label' => 'Phone Number',
                    'settings' => [],
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                    'settings' => [
                        'aria-label' => 'Test label',
                    ],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'required' => true,
                    'label' => 'Comments',
                    'settings' => ['rows' => 8],
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );
    }

    /**
     * Get a mock YamlReader object.
     *
     * @return YamlReader
     */
    protected function getMockTestFormYamlReader()
    {
        if (!isset($this->mockTestFormYamlReader)) {
            $config = Yaml::parse($this->getFixture('configs/feedbackforms/test.yaml'));
            $mock = $this->getMockBuilder(\VuFind\Config\YamlReader::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['get'])
                ->getMock();
            $mock->expects($this->any())->method('get')
                ->with($this->equalTo('FeedbackForms.yaml'))
                ->will($this->returnValue($config));
            $this->mockTestFormYamlReader = $mock;
        }
        return $this->mockTestFormYamlReader;
    }

    /**
     * Get a mock Form object.
     *
     * @param string $formId  Form identifier
     * @param array  $params  Parameters to pass to setFormId
     * @param array  $prefill Prefill data to pass to setFormId
     *
     * @return Form
     * @throws \Exception
     */
    protected function getMockTestForm($formId, $params = [], $prefill = [])
    {
        $form = new Form(
            $this->getMockTestFormYamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class)
        );
        $form->setFormId($formId, $params, $prefill);
        return $form;
    }

    /**
     * Test element options (select, radio, checkbox).
     *
     * @return void
     */
    public function testElementOptions()
    {
        $form = $this->getMockTestForm('TestElementOptions');

        $getElement = function ($name, $elements) {
            foreach ($elements as $el) {
                if ($el['name'] === $name) {
                    return $el;
                }
            }
            return null;
        };

        $elements = $form->getFormElementConfig();

        // Select element optionGroup: options with labels and values
        $el = $getElement('select', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'value' => 'value-1',
                    'label' => 'label-1',
                ],
                'o2' => [
                    'value' => 'value-2',
                    'label' => 'label-2',
                ],
            ],
            $el['optionGroups']['group-1']['options']
        );

        // Select element optionGroup: options with values
        $el = $getElement('select2', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'value' => 'option-1',
                    'label' => 'option-1',
                ],
                'o2' => [
                    'value' => 'option-2',
                    'label' => 'option-2',
                ],
            ],
            $el['optionGroups']['group-1']['options']
        );

        // Select element options with labels and values
        $el = $getElement('select3', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'label-1',
                    'value' => 'value-1',
                ],
                'o2' => [
                    'label' => 'label-2',
                    'value' => 'value-2',
                ],
            ],
            $el['options']
        );

        // Select element options with values
        $el = $getElement('select4', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'option-1',
                    'value' => 'option-1',
                ],
                'o2' => [
                    'label' => 'option-2',
                    'value' => 'option-2',
                ],
            ],
            $el['options']
        );

        // Radio element options with labels and values
        $el = $getElement('radio', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'label-1',
                    'value' => 'value-1',
                ],
                'o2' => [
                    'label' => 'label-2',
                    'value' => 'value-2',
                ],
            ],
            $el['options']
        );

        // Radio element options with values
        $el = $getElement('radio2', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'option-1',
                    'value' => 'option-1',
                ],
                'o2' => [
                    'label' => 'option-2',
                    'value' => 'option-2',
                ],
            ],
            $el['options']
        );

        // Checkbox element options with labels and values
        $el = $getElement('checkbox', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'label-1',
                    'value' => 'value-1',
                ],
                'o2' => [
                    'label' => 'label-2',
                    'value' => 'value-2',
                ],
            ],
            $el['options']
        );

        // Checkbox element options with values
        $el = $getElement('checkbox2', $elements);
        $this->assertEquals(
            [
                'o1' => [
                    'label' => 'option-1',
                    'value' => 'option-1',
                ],
                'o2' => [
                    'label' => 'option-2',
                    'value' => 'option-2',
                ],
            ],
            $el['options']
        );
    }

    /**
     * Test element option value validators (select, radio, checkbox).
     *
     * @return void
     */
    public function testElementOptionValueValidators()
    {
        $form = $this->getMockTestForm('TestElementOptions');

        // Select element optionGroup: options with labels and values
        // Valid option value
        $form->setData(['select' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option values
        $form->setData(['select' => 'invalid-value']);
        $this->assertFalse($form->isValid());
        $form->setData(['select' => 0]);
        $this->assertFalse($form->isValid());
        $form->setData(['select' => 'o11']);
        $this->assertFalse($form->isValid());

        // Select element optionGroup: options with values
        // Valid option value
        $form->setData(['select2' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['select2' => 'invalid-option']);
        $this->assertFalse($form->isValid());

        // Select element options with labels and values
        // Valid option value
        $form->setData(['select3' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['select3' => 'invalid-value']);
        $this->assertFalse($form->isValid());

        // Select element options with values
        // Valid option value
        $form->setData(['select4' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['select4' => 'invalid-option']);
        $this->assertFalse($form->isValid());

        // Radio element options with labels and values
        // Valid option value
        $form->setData(['radio' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['radio' => 'invalid-value']);
        $this->assertFalse($form->isValid());

        // Radio element options with values
        // Valid option value
        $form->setData(['radio2' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['radio2' => 'invalid-option']);
        $this->assertFalse($form->isValid());

        // Checkbox element options with labels and values
        // Valid option value
        $form->setData(['checkbox' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['checkbox' => 'invalid-value']);
        $this->assertFalse($form->isValid());

        // Checkbox element options with values
        // Valid option value
        $form->setData(['checkbox2' => 'o1']);
        $this->assertTrue($form->isValid());
        // Invalid option value
        $form->setData(['checkbox2' => 'invalid-option']);
        $this->assertFalse($form->isValid());
    }

    /**
     * Test checkbox element 'required' and 'requireOne' option validators.
     *
     * @return void
     */
    public function testCheckboxRequiredValidators()
    {
        // Test checkbox with all options required
        $ids = [
            'TestCheckboxWithAllOptionsRequired',  // options with value
            'TestCheckboxWithAllOptionsRequired-2', // options with label and value
        ];

        foreach ($ids as $id) {
            $form = $this->getMockTestForm($id);

            // No options
            $form->setData(['checkbox' => []]);
            $this->assertFalse($form->isValid());

            // One OK option, another missing
            $form->setData(['checkbox' => ['o1']]);
            $this->assertFalse($form->isValid());

            // One OK option, another invalid
            $form->setData(['checkbox' => ['o1', 'invalid-option']]);
            $this->assertFalse($form->isValid());

            // Both required options
            $form->setData(['checkbox' => ['o1', 'o2']]);
            $this->assertTrue($form->isValid());

            // Both required options and one invalid
            $form->setData(['checkbox' => ['o1', 'o2', 'invalid-option']]);
            $this->assertFalse($form->isValid());
        }

        // Test checkbox with one required option
        $ids = [
            'TestCheckboxWithOneOptionRequired',  // options with value
            'TestCheckboxWithOneOptionRequired-2', // options with label and value
        ];

        foreach ($ids as $id) {
            $form = $this->getMockTestForm($id);

            // No options
            $form->setData(['checkbox' => []]);
            $this->assertFalse($form->isValid());

            // One invalid option
            $form->setData(['checkbox' => ['invalid-option']]);
            $this->assertFalse($form->isValid());

            // One OK option
            $form->setData(['checkbox' => ['o1']]);
            $this->assertTrue($form->isValid());

            // One OK option
            $form->setData(['checkbox' => ['o2']]);
            $this->assertTrue($form->isValid());

            // Both options OK
            $form->setData(['checkbox' => ['o1', 'o2']]);
            $this->assertTrue($form->isValid());

            // One OK and one invalid option
            $form->setData(['checkbox' => ['o1', 'invalid-option']]);
            $this->assertFalse($form->isValid());
        }

        // Test checkbox with a single option that is required
        $ids = [
            // options with value
            'TestCheckboxWithOneOptionThatIsRequired',
            // options with label and value
            'TestCheckboxWithOneOptionThatIsRequired-2',
        ];

        foreach ($ids as $id) {
            $form = $this->getMockTestForm($id);

            // No options
            $form->setData(['checkbox' => []]);
            $this->assertFalse($form->isValid());

            // One invalid option
            $form->setData(['checkbox' => ['invalid-option']]);
            $this->assertFalse($form->isValid());

            // One OK option
            $form->setData(['checkbox' => ['o1']]);
            $this->assertTrue($form->isValid());

            // One OK and one invalid option
            $form->setData(['checkbox' => ['o1', 'invalid-option']]);
            $this->assertFalse($form->isValid());
        }

        // Test checkbox with a single option that is required,
        // configured with requireOne
        $ids = [
            // options with value
            'TestCheckboxWithOneOptionThatIsRequiredConfiguredWithRequireOne',
            // options with label and value
            'TestCheckboxWithOneOptionThatIsRequiredConfiguredWithRequireOne-2',
        ];

        foreach ($ids as $id) {
            $form = $this->getMockTestForm($id);

            // No options
            $form->setData(['checkbox' => []]);
            $this->assertFalse($form->isValid());

            // One invalid option
            $form->setData(['checkbox' => ['invalid-option']]);
            $this->assertFalse($form->isValid());

            // One OK option
            $form->setData(['checkbox' => ['o1']]);
            $this->assertTrue($form->isValid());

            // One OK and one invalid option
            $form->setData(['checkbox' => ['o1', 'invalid-option']]);
            $this->assertFalse($form->isValid());
        }
    }

    /**
     * Function to get testEmailSubjects data.
     *
     * @return array
     */
    public static function getEmailSubjectsData(): array
    {
        return [
            'with placeholders' => [
                'TestSubjectEmailWithPlaceholders',
                'Subject One Two option-1',
            ],
            'without placeholders' => [
                'TestSubjectEmailWithoutPlaceholders',
                'Subject without placeholders',
            ],
        ];
    }

    /**
     * Test email subjects.
     *
     * @param string $formToTest      ID of the form to test.
     * @param string $expectedSubject String to be expected.
     *
     * @return void
     *
     * @dataProvider getEmailSubjectsData
     */
    public function testEmailSubjects(
        string $formToTest,
        string $expectedSubject
    ): void {
        $form = $this->getMockTestForm($formToTest);
        $form->setData(
            [
                'text1' => 'One',
                'text2' => 'Two',
                'checkbox' => ['o1'],
            ]
        );
        $this->assertTrue($form->isValid());
        $this->assertEquals(
            $expectedSubject,
            $form->getEmailSubject($form->getData())
        );
    }

    /**
     * Test prefilling values for inputs from form configuration
     *
     * @return void
     */
    public function testPrefill(): void
    {
        $form = $this->getMockTestForm(
            'TestPrefill',
            [],
            [
                'message' => 'Here is your message', // Should be prefilled
                'secret_code' => 'new_secret', // Should be prefilled
                'phone' => '123456789', //Should not be prefilled
            ]
        );
        $this->assertEquals(
            [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'Sender Name',
                    'settings' => [],
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                    'settings' => [],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'label' => 'Comments',
                    'settings' => [
                        'rows' => 8,
                        'value' => 'Here is your message',
                    ],
                ],
                [
                    'type' => 'text',
                    'name' => 'phone',
                    'label' => 'Phone Number',
                    'settings' => [],
                ],
                [
                    'type' => 'hidden',
                    'name' => 'secret_code',
                    'label' => '',
                    'settings' => [
                        'value' => 'new_secret',
                    ],
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );
    }

    /**
     * Test protecting fields from being prefilled
     *
     * @return void
     */
    public function testPrefillProtectedFields(): void
    {
        $form = $this->getMockTestForm(
            'TestPrefillProtectedFields',
            ['userAgent' => 'VuFind Browser 1.0'],
            [
                'userAgent' => 'My Browser 1.0',
                'submit'    => 'Bad submit value',
            ]
        );
        $this->assertEquals(
            [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'Sender Name',
                    'settings' => [],
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                    'settings' => [],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'message',
                    'label' => 'Comments',
                    'settings' => [
                        'rows' => 8,
                    ],
                ],
                [
                    'type' => 'hidden',
                    'name' => 'useragent',
                    'label' => 'User Agent',
                    'settings' => ['value' => 'VuFind Browser 1.0'],
                ],
                [
                    'type' => 'submit',
                    'name' => 'submitButton',
                    'label' => 'Send',
                ],
            ],
            $form->getFormElementConfig()
        );
    }
}
