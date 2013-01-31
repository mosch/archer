<?php
namespace Icecave\Testing\Console\Command;

use Phake;
use PHPUnit_Framework_TestCase;
use ReflectionObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @covers \Icecave\Testing\Console\Command\AbstractPHPUnitCommand
 * @covers \Icecave\Testing\Console\Command\TestCommand
 */
class TestCommandTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->_phpFinder = Phake::mock(
            'Symfony\Component\Process\PhpExecutableFinder'
        );
        $this->_phpunitFinder = Phake::mock(
            'Icecave\Testing\Process\PHPUnitExecutableFinder'
        );
        $this->_phpConfigurationReader = Phake::mock(
            'Icecave\Testing\Configuration\PHPConfigurationReader'
        );
        $this->_configurationFileFinder = Phake::mock(
            'Icecave\Testing\Configuration\ConfigurationFileFinder'
        );
        $this->_processFactory = Phake::mock(
            'Icecave\Testing\Process\ProcessFactory'
        );
        $this->_isolator = Phake::mock('Icecave\Testing\Support\Isolator');
        $this->_command = Phake::partialMock(
            __NAMESPACE__ . '\TestCommand',
            $this->_phpFinder,
            $this->_phpunitFinder,
            $this->_phpConfigurationReader,
            $this->_configurationFileFinder,
            $this->_processFactory,
            $this->_isolator
        );

        $this->_application = Phake::mock('Icecave\Testing\Console\Application');
        $this->_process = Phake::mock('Symfony\Component\Process\Process');

        Phake::when($this->_command)
            ->getApplication(Phake::anyParameters())
            ->thenReturn($this->_application)
        ;

        Phake::when($this->_application)
            ->rawArguments(Phake::anyParameters())
            ->thenReturn(array('foo', 'bar'))
        ;

        Phake::when($this->_phpConfigurationReader)
            ->read(Phake::anyParameters())
            ->thenReturn(array(
                'baz' => 'qux',
                'doom' => 'splat',
            ))
        ;

        Phake::when($this->_configurationFileFinder)
            ->find(Phake::anyParameters())
            ->thenReturn('/path/to/phpunit.xml')
        ;

        Phake::when($this->_processFactory)
            ->createFromArray(Phake::anyParameters())
            ->thenReturn($this->_process)
        ;

        Phake::when($this->_phpFinder)
            ->find(Phake::anyParameters())
            ->thenReturn('/path/to/php')
        ;

        Phake::when($this->_phpunitFinder)
            ->find(Phake::anyParameters())
            ->thenReturn('/path/to/phpunit')
        ;

        $this->_reflector = new ReflectionObject($this->_command);
        $this->_executeMethod = $this->_reflector->getMethod('execute');
        $this->_executeMethod->setAccessible(true);

        $this->_input = Phake::mock('Symfony\Component\Console\Input\InputInterface');

        // used for closures
        $that = $this;

        $this->_stdErr = '';
        $this->_errorOutput = Phake::mock('Symfony\Component\Console\Output\OutputInterface');
        Phake::when($this->_errorOutput)
            ->write(Phake::anyParameters())
            ->thenGetReturnByLambda(function ($data) use ($that) {
                $that->_stdErr .= $data;
            })
        ;

        $this->_stdOut = '';
        $this->_output = Phake::mock('Symfony\Component\Console\Output\ConsoleOutputInterface');
        Phake::when($this->_output)
            ->write(Phake::anyParameters())
            ->thenGetReturnByLambda(function ($data) use ($that) {
                $that->_stdOut .= $data;
            })
        ;
        Phake::when($this->_output)
            ->writeln(Phake::anyParameters())
            ->thenGetReturnByLambda(function ($data) use ($that) {
                $that->_stdOut .= $data . "\n";
            })
        ;
        Phake::when($this->_output)
            ->getErrorOutput(Phake::anyParameters())
            ->thenReturn($this->_errorOutput)
        ;

        Phake::when($this->_process)
            ->run(Phake::anyParameters())
            ->thenGetReturnByLambda(function ($callback) {
                $callback('out', "out\nout\n");
                $callback('err', "err\nerr\n");

                return 111;
            })
        ;
    }

    public function testConstructor()
    {
        $this->assertSame($this->_phpFinder, $this->_command->phpFinder());
        $this->assertSame($this->_phpunitFinder, $this->_command->phpunitFinder());
        $this->assertSame($this->_phpConfigurationReader, $this->_command->phpConfigurationReader());
        $this->assertSame($this->_configurationFileFinder, $this->_command->configurationFileFinder());
        $this->assertSame($this->_processFactory, $this->_command->processFactory());
    }

    public function testConstructorDefaults()
    {
        $this->_command = new TestCommand;

        $this->assertInstanceOf(
            'Symfony\Component\Process\PhpExecutableFinder',
            $this->_command->phpFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Testing\Process\PHPUnitExecutableFinder',
            $this->_command->phpunitFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Testing\Configuration\PHPConfigurationReader',
            $this->_command->phpConfigurationReader()
        );
        $this->assertInstanceOf(
            'Icecave\Testing\Configuration\ConfigurationFileFinder',
            $this->_command->configurationFileFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Testing\Process\ProcessFactory',
            $this->_command->processFactory()
        );
    }

    public function testConfigure()
    {
        $expectedInputDefinition = new InputDefinition;
        $expectedInputDefinition->addArgument(new InputArgument(
            'argument',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Argument(s) to pass to PHPUnit.'
        ));

        $this->assertSame('test', $this->_command->getName());
        $this->assertSame(
            'Run the test suite for a project.',
            $this->_command->getDescription()
        );
        $this->assertEquals(
            $expectedInputDefinition,
            $this->_command->getDefinition()
        );
    }

    public function testExecute()
    {
        $exitCode = $this->_executeMethod->invoke(
            $this->_command,
            $this->_input,
            $this->_output
        );
        $expectedStdout = <<<'EOD'
<info>Using PHP:</info> /path/to/php
<info>Using PHPUnit:</info> /path/to/phpunit
out
out

EOD;
        $expectedStderr = <<<'EOD'
err
err

EOD;

        $this->assertSame(111, $exitCode);
        $this->assertSame($expectedStdout, $this->_stdOut);
        $this->assertSame($expectedStderr, $this->_stdErr);
        Phake::inOrder(
            Phake::verify($this->_phpFinder)->find(),
            Phake::verify($this->_phpunitFinder)->find(),
            Phake::verify($this->_phpConfigurationReader)
                ->read(Phake::capture($actualPhpConfigurationPaths)),
            Phake::verify($this->_configurationFileFinder)->find(
                Phake::capture($actualPhpunitConfigurationPaths),
                './vendor/icecave/testing/res/phpunit/phpunit.xml'
            ),
            Phake::verify($this->_processFactory)
                ->createFromArray(Phake::capture($actualArguments)),
            Phake::verify($this->_command)->passthru(
                $this->identicalTo($this->_process),
                $this->identicalTo($this->_output)
            )
        );
        $this->assertSame(array(
            './vendor/icecave/testing/res/php/php.ini',
            './test/php.ini',
            './php.ini',
        ), $actualPhpConfigurationPaths);
        $this->assertSame(array(
            './phpunit.xml',
            './phpunit.xml.dist',
            './test/phpunit.xml',
            './test/phpunit.xml.dist',
        ), $actualPhpunitConfigurationPaths);
        $this->assertSame(array(
            '/path/to/php',
            '--define',
            'baz=qux',
            '--define',
            'doom=splat',
            '/path/to/phpunit',
            '--configuration',
            '/path/to/phpunit.xml',
            'bar',
        ), $actualArguments);
    }
}
