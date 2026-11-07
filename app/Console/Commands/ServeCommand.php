<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as LaravelServeCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

class ServeCommand extends LaravelServeCommand
{
    protected $description = 'Serve the application with its queue listener and scheduler';

    public function handle()
    {
        if ($this->option('without-workers')) {
            return parent::handle();
        }

        $processes = [];
        $stop = static function () use (&$processes): void {
            foreach (array_reverse($processes) as $process) {
                $process->stop(3);
            }
            $processes = [];
        };

        // Also clean up when Artisan exits from a signal handler.
        register_shutdown_function($stop);
        $this->trap(fn () => [SIGTERM, SIGINT, SIGHUP, SIGQUIT], function () use ($stop) {
            $stop();
            exit(0);
        });

        try {
            foreach ($this->processCommands() as $name => $arguments) {
                $process = $this->makeProcess($arguments);
                $processes[$name] = $process;
                $process->start(function ($type, $buffer) use ($name): void {
                    $this->output->write("[$name] ".$buffer);
                });
            }

            $this->components->info('Server, queue listener and scheduler started. Press Ctrl+C to stop all.');

            while (true) {
                foreach ($processes as $name => $process) {
                    $running = $process->isRunning();
                    // Drain pipes and discard captured output to bound memory usage.
                    $process->clearOutput();
                    $process->clearErrorOutput();

                    if (! $running) {
                        $status = $process->getExitCode() ?? 1;
                        $this->components->error("The {$name} process stopped (exit {$status}). Stopping all processes.");

                        return $status ?: 1;
                    }
                }

                usleep(200_000);
            }
        } finally {
            $stop();
        }
    }

    protected function processCommands(): array
    {
        $server = ['serve', '--without-workers', '--host='.$this->option('host'), '--tries='.$this->option('tries')];
        if ($this->option('port') !== null) {
            $server[] = '--port='.$this->option('port');
        }
        if ($this->option('no-reload')) {
            $server[] = '--no-reload';
        }

        $connection = (string) config('queue.default');
        $connection = $connection === 'sync' ? 'database' : $connection;
        $queues = implode(',', array_unique(array_filter([
            (string) config("queue.connections.{$connection}.queue", 'default'),
            'default',
        ])));

        $commands = [
            'server' => $server,
            // Run ready jobs immediately; yield briefly when the queue is empty.
            'queue' => ['queue:listen', $connection, '--queue='.$queues, '--sleep=0.25', '--rest=0', '--timeout=60', '--no-interaction'],
            'scheduler' => ['schedule:work', '--no-interaction'],
        ];

        if ($this->option('env') !== null) {
            foreach ($commands as &$command) {
                $command[] = '--env='.$this->option('env');
            }
        }

        return $commands;
    }

    protected function makeProcess(array $arguments): Process
    {
        return new Process(
            [php_binary(), base_path('artisan'), ...$arguments],
            base_path(),
            ['APP_SERVE_QUEUE_RUNNING' => '1'],
            timeout: null,
        );
    }

    protected function getOptions()
    {
        return [
            ...parent::getOptions(),
            ['without-workers', null, InputOption::VALUE_NONE, 'Run only the HTTP development server'],
        ];
    }
}
