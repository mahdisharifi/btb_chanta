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
