<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Tests\TestCase;

class SharedHostingQueueScheduleTest extends TestCase
{
    public function test_worker_uses_scheduler_php_with_overlap_protection_and_private_output(): void
    {
        $worker = $this->workerForQueue('default');

        $this->assertStringStartsWith(Application::formatCommandString('queue:work'), $worker->command);
        $this->assertStringContainsString('--stop-when-empty', $worker->command);
        $this->assertStringContainsString('--max-time=55', $worker->command);
        $this->assertStringContainsString('--timeout=60', $worker->command);
        $this->assertStringNotContainsString('--tries=', $worker->command);
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertTrue($worker->withoutOverlapping);
        $this->assertSame(5, $worker->expiresAt);
        $this->assertFalse($worker->runInBackground);
        $this->assertSame(storage_path('logs/queue-worker.log'), $worker->output);
        $this->assertTrue($worker->shouldAppendOutput);
    }

    public function test_worker_consumes_configured_queue_and_legacy_finalization_queue_once_each(): void
    {
        $worker = $this->workerForQueue('processing');
        $this->assertStringContainsString('processing,default', $worker->command);

        $worker = $this->workerForQueue('default');
        $this->assertStringNotContainsString('default,default', $worker->command);
        $this->assertStringContainsString('--queue=', $worker->command);
    }

    public function test_hosting_worker_is_skipped_only_while_serve_manages_the_queue(): void
    {
        $previous = getenv('APP_SERVE_QUEUE_RUNNING');
        try {
            putenv('APP_SERVE_QUEUE_RUNNING=1');
            $this->assertFalse($this->workerForQueue('default')->filtersPass($this->app));
            putenv('APP_SERVE_QUEUE_RUNNING');
            $this->assertTrue($this->workerForQueue('default')->filtersPass($this->app));
        } finally {
            putenv($previous === false ? 'APP_SERVE_QUEUE_RUNNING' : 'APP_SERVE_QUEUE_RUNNING='.$previous);
        }
    }

    private function workerForQueue(string $queue): Event
    {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.queue', $queue);
        $schedule = new Schedule;
        ScheduleFacade::swap($schedule);

        require base_path('routes/console.php');

        $workers = array_values(array_filter(
            $schedule->events(),
            static fn (Event $event): bool => $event->description === 'shared-hosting-queue-worker',
        ));
        $this->assertCount(1, $workers);

        return $workers[0];
    }
}
