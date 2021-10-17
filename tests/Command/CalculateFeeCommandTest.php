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
//        file_put_contents(getcwd() . '/assets/out.csv', $output);
        dd($output);

//        $this->assertStringContainsString('Detected name', $output);
//        $this->assertStringContainsString('Company 3', $output);
//        $this->assertStringContainsString('Company 1', $output);
//        $this->assertStringNotContainsString('violations', $output);
//        $this->assertStringNotContainsString('No duplicates', $output);
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }
}
