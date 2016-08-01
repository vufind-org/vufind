<?php
/**
 * Mailer Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
namespace VuFindTest\Mailer;
use VuFind\Mailer\Mailer;
use Zend\Mail\Address;
use Zend\Mail\AddressList;

/**
 * Mailer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MailerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test sending an email.
     *
     * @return void
     */
    public function testSend()
    {
        $callback = function ($message) {
            return '<to@example.com>' == $message->getTo()->current()->toString()
                && '<from@example.com>' == $message->getFrom()->current()->toString()
                && 'body' == $message->getBody()
                && 'subject' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test sending an email using an address object for the From field.
     *
     * @return void
     */
    public function testSendWithAddressObjectInSender()
    {
        $callback = function ($message) {
            $fromString = $message->getFrom()->current()->toString();
            return '<to@example.com>' == $message->getTo()->current()->toString()
                && 'Sender TextName <from@example.com>' == $fromString
                && 'body' == $message->getBody()
                && 'subject' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $address = new Address('from@example.com', 'Sender TextName');
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', $address, 'subject', 'body');
    }

    /**
     * Test sending an email using an address object for the To field.
     *
     * @return void
     */
    public function testSendWithAddressObjectInRecipient()
    {
        $callback = function ($message) {
            $fromString = $message->getFrom()->current()->toString();
            return 'Recipient TextName <to@example.com>' == $message->getTo()->current()->toString()
                && '<from@example.com>' == $message->getFrom()->current()->toString()
                && 'body' == $message->getBody()
                && 'subject' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $address = new Address('to@example.com', 'Recipient TextName');
        $mailer = new Mailer($transport);
        $mailer->send($address, 'from@example.com', 'subject', 'body');
    }

    /**
     * Test sending an email using an address list object for the To field.
     *
     * @return void
     */
    public function testSendWithAddressListObjectInRecipient()
    {
        $callback = function ($message) {
            $fromString = $message->getFrom()->current()->toString();
            return 'Recipient TextName <to@example.com>' == $message->getTo()->current()->toString()
                && '<from@example.com>' == $message->getFrom()->current()->toString()
                && 'body' == $message->getBody()
                && 'subject' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $list = new AddressList();
        $list->add(new Address('to@example.com', 'Recipient TextName'));
        $mailer = new Mailer($transport);
        $mailer->send($list, 'from@example.com', 'subject', 'body');
    }

    /**
     * Test bad to address.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Recipient Email Address
     */
    public function testBadTo()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('bad@bad', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test empty to address.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Recipient Email Address
     */
    public function testEmptyTo()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test that we only accept one recipient by default
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Too Many Email Recipients
     */
    public function testTooManyRecipients()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('one@test.com;two@test.com', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test bad from address.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Sender Email Address
     */
    public function testBadFrom()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', 'bad@bad', 'subject', 'body');
    }

    /**
     * Test bad from address in Address object.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Sender Email Address
     */
    public function testBadFromInAddressObject()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', new Address('bad@bad'), 'subject', 'body');
    }

    /**
     * Test transport exception.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Boom
     */
    public function testTransportException()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->will($this->throwException(new \Exception('Boom')));
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test sendLink
     *
     * @return void
     */
    public function testSendLink()
    {
        $viewCallback = function ($in) {
            return $in['msgUrl'] == 'http://foo'
                && $in['to'] == 'to@example.com;to2@example.com'
                && $in['from'] == 'from@example.com'
                && $in['message'] == 'message';
        };
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer', ['partial']);
        $view->expects($this->once())->method('partial')
            ->with($this->equalTo('Email/share-link.phtml'), $this->callback($viewCallback))
            ->will($this->returnValue('body'));

        $callback = function ($message) {
            $to = $message->getTo();
            return $to->has('to@example.com')
                && $to->has('to2@example.com')
                && 2 == count($to)
                && '<from@example.com>' == $message->getFrom()->current()->toString()
                && '<cc@example.com>' == $message->getCc()->current()->toString()
                && 'body' == $message->getBody()
                && 'Library Catalog Search Result' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $mailer = new Mailer($transport);
        $mailer->setMaxRecipients(2);
        $mailer->sendLink(
            'to@example.com;to2@example.com', 'from@example.com', 'message', 'http://foo', $view, null,
            'cc@example.com'
        );
    }

    /**
     * Test sendRecord
     *
     * @return void
     */
    public function testSendRecord()
    {
        $driver = $this->getMock('VuFind\RecordDriver\AbstractBase');
        $driver->expects($this->once())->method('getBreadcrumb')->will($this->returnValue('breadcrumb'));

        $viewCallback = function ($in) use ($driver) {
            return $in['driver'] == $driver
                && $in['to'] == 'to@example.com'
                && $in['from'] == 'from@example.com'
                && $in['message'] == 'message';
        };
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer', ['partial']);
        $view->expects($this->once())->method('partial')
            ->with($this->equalTo('Email/record.phtml'), $this->callback($viewCallback))
            ->will($this->returnValue('body'));

        $callback = function ($message) {
            return '<to@example.com>' == $message->getTo()->current()->toString()
                && '<from@example.com>' == $message->getFrom()->current()->toString()
                && 'body' == $message->getBody()
                && 'Library Catalog Record: breadcrumb' == $message->getSubject();
        };
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->with($this->callback($callback));
        $mailer = new Mailer($transport);
        $mailer->sendRecord('to@example.com', 'from@example.com', 'message', $driver, $view);
    }
}
