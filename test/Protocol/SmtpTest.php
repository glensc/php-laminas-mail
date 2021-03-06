<?php

/**
 * @see       https://github.com/laminas/laminas-mail for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mail/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mail/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mail\Protocol;

use Laminas\Mail\Headers;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp;
use LaminasTest\Mail\TestAsset\SmtpProtocolSpy;
use PHPUnit\Framework\TestCase;

/**
 * @group      Laminas_Mail
 * @covers Laminas\Mail\Protocol\Smtp<extended>
 */
class SmtpTest extends TestCase
{
    /** @var Smtp */
    public $transport;
    /** @var SmtpProtocolSpy */
    public $connection;

    public function setUp()
    {
        $this->transport  = new Smtp();
        $this->connection = new SmtpProtocolSpy();
        $this->transport->setConnection($this->connection);
    }

    public function testSendMinimalMail()
    {
        $headers = new Headers();
        $headers->addHeaderLine('Date', 'Sun, 10 Jun 2012 20:07:24 +0200');

        $message = new Message();
        $message->setHeaders($headers);
        $message->setSender('sender@example.com', 'Example Sender');
        $message->setBody('testSendMailWithoutMinimalHeaders');
        $message->addTo('recipient@example.com', 'Recipient Name');

        $expectedMessage = "EHLO localhost\r\n"
            . "MAIL FROM:<sender@example.com>\r\n"
            . "RCPT TO:<recipient@example.com>\r\n"
            . "DATA\r\n"
            . "Date: Sun, 10 Jun 2012 20:07:24 +0200\r\n"
            . "Sender: Example Sender <sender@example.com>\r\n"
            . "To: Recipient Name <recipient@example.com>\r\n"
            . "\r\n"
            . "testSendMailWithoutMinimalHeaders\r\n"
            . ".\r\n";

        $this->transport->send($message);

        $this->assertEquals($expectedMessage, $this->connection->getLog());
    }

    public function testSendEscapedEmail()
    {
        $headers = new Headers();
        $headers->addHeaderLine('Date', 'Sun, 10 Jun 2012 20:07:24 +0200');

        $message = new Message();
        $message->setHeaders($headers);
        $message->setSender('sender@example.com', 'Example Sender');
        $message->setBody("This is a test\n.");
        $message->addTo('recipient@example.com', 'Recipient Name');

        $expectedMessage = "EHLO localhost\r\n"
            . "MAIL FROM:<sender@example.com>\r\n"
            . "RCPT TO:<recipient@example.com>\r\n"
            . "DATA\r\n"
            . "Date: Sun, 10 Jun 2012 20:07:24 +0200\r\n"
            . "Sender: Example Sender <sender@example.com>\r\n"
            . "To: Recipient Name <recipient@example.com>\r\n"
            . "\r\n"
            . "This is a test\r\n"
            . "..\r\n"
            . ".\r\n";

        $this->transport->send($message);

        $this->assertEquals($expectedMessage, $this->connection->getLog());
    }

    public function testDisconnectCallsQuit()
    {
        $this->connection->disconnect();
        $this->assertTrue($this->connection->calledQuit);
    }

    public function testDisconnectResetsAuthFlag()
    {
        $this->connection->connect();
        $this->connection->setSessionStatus(true);
        $this->connection->setAuth(true);
        $this->assertTrue($this->connection->getAuth());
        $this->connection->disconnect();
        $this->assertFalse($this->connection->getAuth());
    }

    public function testConnectHasVerboseErrors()
    {
        $smtp = new TestAsset\ErroneousSmtp();

        $this->expectException('Laminas\Mail\Protocol\Exception\RuntimeException');
        $this->expectExceptionMessageRegExp('/nonexistentremote/');

        $smtp->connect('nonexistentremote');
    }

    public function testCanAvoidQuitRequest()
    {
        $this->assertTrue($this->connection->useCompleteQuit(), 'Default behaviour must be BC');

        $this->connection->resetLog();
        $this->connection->connect();
        $this->connection->helo();
        $this->connection->disconnect();

        $this->assertContains('QUIT', $this->connection->getLog());

        $this->connection->setUseCompleteQuit(false);
        $this->assertFalse($this->connection->useCompleteQuit());

        $this->connection->resetLog();
        $this->connection->connect();
        $this->connection->helo();
        $this->connection->disconnect();

        $this->assertNotContains('QUIT', $this->connection->getLog());

        $connection = new SmtpProtocolSpy([
            'use_complete_quit' => false,
        ]);
        $this->assertFalse($connection->useCompleteQuit());
    }

    public function testAuthThrowsWhenAlreadyAuthed()
    {
        $this->connection->setAuth(true);
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('Already authenticated for this session');
        $this->connection->auth();
    }

    public function testHeloThrowsWhenAlreadySession()
    {
        $this->connection->helo('hostname.test');
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('Cannot issue HELO to existing session');
        $this->connection->helo('hostname.test');
    }

    public function testHeloThrowsWithInvalidHostname()
    {
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('The input does not match the expected structure for a DNS hostname');
        $this->connection->helo("invalid\r\nhost name");
    }

    public function testMailThrowsWhenNoSession()
    {
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('A valid session has not been started');
        $this->connection->mail('test@example.com');
    }

    public function testRcptThrowsWhenNoMail()
    {
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('No sender reverse path has been supplied');
        $this->connection->rcpt('test@example.com');
    }

    public function testDataThrowsWhenNoRcpt()
    {
        $this->expectException('Laminas\Mail\Exception\RuntimeException');
        $this->expectExceptionMessage('No recipient forward path has been supplied');
        $this->connection->data('message');
    }
}
