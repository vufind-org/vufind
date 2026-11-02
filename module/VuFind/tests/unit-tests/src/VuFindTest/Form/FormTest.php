<?php

/**
 * Form Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Config\ConfigManagerInterface;
use VuFind\Form\Form;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * Form Test Class.
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
    use ConfigRelatedServicesTrait;

    /**
     * Get a mock Form object.
     *
     * @param ?string $formId               Form identifier
     * @param array   $params               Parameters to pass to setFormId
     * @param array   $prefill              Prefill data to pass to setFormId
     * @param array   $config               VuFind config
     * @param bool    $useFormConfigFixture If the test feedback form config fixtrue should be used
     *
     * @return Form
     * @throws \Exception
     */
    protected function getMockTestForm(
        ?string $formId = null,
        array $params = [],
        array $prefill = [],
        array $config = [],
        bool $useFormConfigFixture = true
    ): Form {
        $feedBackFormConfig = $useFormConfigFixture
            ? $this->getContainerWithConfigRelatedServices(
                baseDir: $this->getFixtureDir() . 'configs/feedbackforms',
                baseSubDir: ''
            )->get(ConfigManagerInterface::class)
                ->getConfigArray('test')
            : $this->getContainerWithConfigRelatedServices()
                ->get(ConfigManagerInterface::class)
                ->getConfigArray('FeedbackForms');
        $form = new Form(
            $this->createMock(\Laminas\View\HelperPluginManager::class),
            $this->createMock(\VuFind\Form\Handler\PluginManager::class),
            $config,
            $feedBackFormConfig
        );
        if ($formId !== null) {
            $form->setFormId($formId, $params, $prefill);
        }
        return $form;
    }

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithoutConfiguration(): void
    {
        $form = $this->getMockTestForm(
            useFormConfigFixture: false
        );
        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->useCaptcha());
        $this->assertFalse($form->showOnlyForLoggedUsers());
        $this->assertSame([], $form->getFormElementConfig());
        $this->assertEquals(
            [['email' => null, 'name' => null]],
            $form->getRecipient()
        );
        $this->assertNull($form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertSame('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertSame(
            'feedback_response',
            $form->getSubmitResponse()
        );
        $this->assertSame([[], 'Email/form.phtml'], $form->formatEmailMessage([]));
        $this->assertSame([], $form->mapRequestParamsToFieldValues([]));

        $this->assertInstanceOf(
            \Laminas\InputFilter\InputFilter::class,
            $form->getInputFilter()
        );
        $this->assertCount(0, $form->getSecondaryHandlers());
    }

    /**
     * Test defaults with defaults passed to constructor.
     *
     * @return void
     */
    public function testDefaultsWithConfiguration(): void
    {
        $defaults = [
            'recipient_email' => 'me@example.com',
            'recipient_name' => 'me',
            'email_subject' => 'subject',
        ];
        $form = $this->getMockTestForm(
            config: ['Feedback' => $defaults],
            useFormConfigFixture: false
        );
        $this->assertSame(
            [['email' => 'me@example.com', 'name' => 'me']],
            $form->getRecipient()
        );
        $this->assertSame('subject', $form->getEmailSubject([]));
    }

    /**
     * Test that the class blocks unknown form IDs.
     *
     * @return void
     */
    public function testUndefinedFormId(): void
    {
        $this->expectException(\VuFind\Exception\RecordMissing::class);
        $this->expectExceptionMessage('Form \'foo\' not found');

        $this->getMockTestForm(
            'foo',
            useFormConfigFixture: false
        );
    }

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithFormSet(): void
    {
        $form = $this->getMockTestForm(
            'FeedbackSite',
            useFormConfigFixture: false
        );

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
                    'autocomplete' => 'email',
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

        $this->assertSame('feedback_title', $form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertSame('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertSame(
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
                'autocomplete' => 'email',
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
        $this->assertInstanceOf(
            \Laminas\InputFilter\InputFilter::class,
            $form->getInputFilter()
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
    public function testSenderFieldMerging(): void
    {
        $form = $this->getMockTestForm(
            'FeedbackSite',
            useFormConfigFixture: false
        );

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
                    'autocomplete' => 'email',
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
                    'autocomplete' => 'email',
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
    public function testSenderFieldMergingWithSettings(): void
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
                    'autocomplete' => 'email',
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
     * Test element options (select, radio, checkbox).
     *
     * @return void
     */
    public function testElementOptions(): void
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
    public function testElementOptionValueValidators(): void
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
    public function testCheckboxRequiredValidators(): void
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
     * @return \Iterator
     */
    public static function getEmailSubjectsData(): \Iterator
    {
        yield 'with placeholders' => [
            'TestSubjectEmailWithPlaceholders',
            'Subject One Two option-1',
        ];
        yield 'without placeholders' => [
            'TestSubjectEmailWithoutPlaceholders',
            'Subject without placeholders',
        ];
    }

    /**
     * Test email subjects.
     *
     * @param string $formToTest      ID of the form to test.
     * @param string $expectedSubject String to be expected.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getEmailSubjectsData')]
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
        $this->assertSame(
            $expectedSubject,
            $form->getEmailSubject($form->getData())
        );
    }

    /**
     * Function to get form action route test data.
     *
     * @return \Iterator
     */
    public static function getFormActionRouteData(): \Iterator
    {
        yield 'with no route set' => [
            'TestWithNoFormActionRouteSet',
            'feedback-form',
        ];
        yield 'with route set' => [
            'TestWithFormActionRouteSet',
            'test-action',
        ];
    }

    /**
     * Test formActionRoute setting.
     *
     * @param string $id       Form id
     * @param string $expected Expected value
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getFormActionRouteData')]
    public function testFormActionRoute(string $id, string $expected): void
    {
        $form = $this->getMockTestForm($id);
        $this->assertSame($expected, $form->getFormActionRoute());
    }

    /**
     * Test prefilling values for inputs from form configuration.
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
        $this->assertSame(
            [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Sender Name',
                    'group' => '__sender__',
                    'settings' => [],
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'feedback_email',
                    'group' => '__sender__',
                    'autocomplete' => 'email',
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
     * Test protecting fields from being prefilled.
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
        $this->assertSame(
            [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Sender Name',
                    'group' => '__sender__',
                    'settings' => [],
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'feedback_email',
                    'group' => '__sender__',
                    'autocomplete' => 'email',
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
                    'settings' => ['value' => 'VuFind Browser 1.0'],
                    'label' => 'User Agent',
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
