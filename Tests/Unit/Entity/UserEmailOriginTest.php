<?php

namespace Oro\Bundle\ImapBundle\Tests\Unit\Entity;

use Oro\Bundle\EmailBundle\Tests\Unit\ReflectionUtil;
use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;

class UserEmailOriginTest extends \PHPUnit_Framework_TestCase
{
    public function testGetId()
    {
        $origin = new UserEmailOrigin();
        ReflectionUtil::setId($origin, 123);
        $this->assertEquals(123, $origin->getId());
    }

    public function testImapHostGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertNull($origin->getImapHost());
        $origin->setImapHost('test');
        $this->assertEquals('test', $origin->getImapHost());
    }

    public function testImapPortGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertEquals(0, $origin->getImapPort());
        $origin->setImapPort(123);
        $this->assertEquals(123, $origin->getImapPort());
    }

    public function testSslGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertNull($origin->getSsl());
        $origin->setSsl('test');
        $this->assertEquals('test', $origin->getSsl());
    }

    public function testUserGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertNull($origin->getUser());
        $origin->setUser('test');
        $this->assertEquals('test', $origin->getUser());
    }

    public function testPasswordGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertNull($origin->getPassword());
        $origin->setPassword('test');
        $this->assertEquals('test', $origin->getPassword());
    }

    public function testSmtpHostGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertNull($origin->getSmtpHost());
        $origin->setSmtpHost('test');
        $this->assertEquals('test', $origin->getSmtpHost());
    }

    public function testSmtpPortGetterAndSetter()
    {
        $origin = new UserEmailOrigin();
        $this->assertEquals(0, $origin->getSmtpPort());
        $origin->setSmtpPort(123);
        $this->assertEquals(123, $origin->getSmtpPort());
    }
}