<?php
/**
 * Form Test Class
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Form;

use VuFind\Config\YamlReader;
use VuFind\Form\Form;

/**
 * Form Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FormTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithoutConfiguration()
    {
        $form = new Form(new YamlReader());
        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->useCaptcha());
        $this->assertFalse($form->showOnlyForLoggedUsers());
        $this->assertEquals([], $form->getElements());
        $this->assertEquals(
            [['email' => null, 'name' => null]], $form->getRecipient()
        );
        $this->assertNull($form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertEquals('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertEquals(
            'Thank you for your feedback.', $form->getSubmitResponse()
        );
        $this->assertEquals([[], 'Email/form.phtml'], $form->formatEmailMessage([]));
        $this->assertEquals(
            'Zend\InputFilter\InputFilter', get_class($form->getInputFilter())
        );
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
        $form = new Form(new YamlReader(), $defaults);
        $this->assertEquals(
            [['name' => 'me', 'email' => 'me@example.com']], $form->getRecipient()
        );
        $this->assertEquals('subject', $form->getEmailSubject([]));
    }

    /**
     * Test that the class blocks unknown form IDs.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\RecordMissing
     * @expectedExceptionMessage Form 'foo' not found
     */
    public function testUndefinedFormId()
    {
        $form = new Form(new YamlReader());
        $form->setFormId('foo');
    }

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithFormSet()
    {
        $form = new Form(new YamlReader());
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
                    'settings' => ['cols' => 50, 'rows' => 8],
                ],
                [
                    'type' => 'text',
                    'name' => 'name',
                    'group' => '__sender__',
                    'label' => 'feedback_name',
                    'settings' => ['size' => 50],
                ],
                [
                    'type' => 'email',
                    'name' => 'email',
                    'group' => '__sender__',
                    'label' => 'feedback_email',
                    'settings' => ['size' => 50],
                ],
                [
                    'type' => 'submit',
                    'name' => 'submit',
                    'label' => 'Send',
                ],
            ],
            $form->getElements()
        );
        $this->assertEquals(
            [['email' => null, 'name' => null]], $form->getRecipient()
        );
        $this->assertEquals('Send us your feedback!', $form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertEquals('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertEquals(
            'Thank you for your feedback.', $form->getSubmitResponse()
        );
        $this->assertEquals(
            [
                [
                    ['type' => 'textarea', 'value' => 'x', 'label' => 'Comments'],
                    ['type' => 'text', 'value' => 'y', 'label' => 'feedback_name'],
                    ['type' => 'email', 'value' => 'z@foo.com', 'label' => 'feedback_email'],
                ],
                'Email/form.phtml'
            ],
            $form->formatEmailMessage(
                [
                    'message' => 'x',
                    'name' => 'y',
                    'email' => 'z@foo.com'
                ]
            )
        );
        $this->assertEquals(
            'Zend\InputFilter\InputFilter', get_class($form->getInputFilter())
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
}
