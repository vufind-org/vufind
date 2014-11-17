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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Mailer;
use VuFind\Mailer\Mailer;

/**
 * Mailer Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
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
     * Test bad to address.
     *
     * @return void
     * @expectedException VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Recipient Email Address
     */
    public function testBadTo()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('bad@bad', 'from@example.com', 'subject', 'body');
    }

    /**
     * Test bad from address.
     *
     * @return void
     * @expectedException VuFind\Exception\Mail
     * @expectedExceptionMessage Invalid Sender Email Address
     */
    public function testBadFrom()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', 'bad@bad', 'subject', 'body');
    }

    /**
     * Test transport exception.
     *
     * @return void
     * @expectedException VuFind\Exception\Mail
     * @expectedExceptionMessage Boom
     */
    public function testTransportException()
    {
        $transport = $this->getMock('Zend\Mail\Transport\TransportInterface');
        $transport->expects($this->once())->method('send')->will($this->throwException(new \Exception('Boom')));
        $mailer = new Mailer($transport);
        $mailer->send('to@example.com', 'from@example.com', 'subject', 'body');
    }
}