<?php

namespace Tests\Feature;

use App\Console\Commands\ServeCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Queue\Listener;
use Illuminate\Queue\ListenerOptions;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServeCommandTest extends TestCase
{
    public function test_serve_registers_workers_and_preserves_server_and_environment_options(): void
    {
        $this->assertInstanceOf(ServeCommand::class, app(Kernel::class)->all()['serve']);
        config(['queue.default' => 'sync', 'queue.connections.database.queue' => 'processing']);

        $command = new class extends ServeCommand
        {
            public function commands(array $options): array
            {
                $this->getDefinition()->addOption(new InputOption('env', null, InputOption::VALUE_REQUIRED));
                $this->input = new ArrayInput($options, $this->getDefinition());

                return $this->processCommands();
            }
        };
        $command->setLaravel($this->app);
        $commands = $command->commands(['--host' => '0.0.0.0', '--port' => '8123', '--no-reload' => true, '--env' => 'testing']);

        $this->assertContains('--without-workers', $commands['server']);
        $this->assertContains('--host=0.0.0.0', $commands['server']);
        $this->assertContains('--port=8123', $commands['server']);
        $this->assertContains('--no-reload', $commands['server']);
        $this->assertSame('database', $commands['queue'][1]);
        $this->assertContains('--queue=processing,default', $commands['queue']);
        $this->assertContains('--sleep=0.25', $commands['queue']);
        $this->assertContains('--rest=0', $commands['queue']);
        $timeoutArgument = array_values(array_filter($commands['queue'], fn ($argument) => str_starts_with($argument, '--timeout=')))[0];
        $listener = new Listener(base_path());
        $worker = $listener->makeProcess('database', 'processing,default', new ListenerOptions(
            timeout: (int) substr($timeoutArgument, strlen('--timeout=')),
        ));
        $this->assertNull($worker->getTimeout(), 'Development queue jobs must not time out and stop the server.');
        foreach ($commands as $arguments) {
            $this->assertContains('--env=testing', $arguments);
        }
    }

    public function test_failed_child_stops_all_processes_and_returns_failure(): void
    {
        $processes = [];
        foreach ([true, false, true] as $running) {
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('start')->once();
            $process->shouldReceive('isRunning')->andReturn($running);
            $process->shouldReceive('clearOutput')->zeroOrMoreTimes();
            $process->shouldReceive('clearErrorOutput')->zeroOrMoreTimes();
            $process->shouldReceive('getExitCode')->zeroOrMoreTimes()->andReturn(7);
            $process->shouldReceive('stop')->once()->with(3);
            $processes[] = $process;
        }

        $command = new class($processes) extends ServeCommand
        {
            public function __construct(private array $fakeProcesses)
            {
                parent::__construct();
            }

            protected function makeProcess(array $arguments): Process
            {
                return array_shift($this->fakeProcesses);
            }
        };

        $this->app->instance(ServeCommand::class, $command);
        $this->artisan('serve')->assertExitCode(7);
    }
}
