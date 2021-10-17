<?php

declare(strict_types=1);

namespace Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CalculateFeeCommandTest extends KernelTestCase
{
    public function testProvidedInput()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('fee:calculate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['filePath' => getcwd() . '/assets/input.csv', 'demoMode' => true]);

        $output = $commandTester->getDisplay();

        $this->assertEquals(md5_file(getcwd() . '/assets/output.csv'), md5($output));
        $this->assertEquals(sha1_file(getcwd() . '/assets/output.csv'), sha1($output));
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }
}
