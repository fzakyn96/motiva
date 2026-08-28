<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestRedisJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('MOTIVA Redis Queue Test', [
            'executed_at' => now()->toDateTimeString(),
            'message' => 'Redis queue is working correctly.',
        ]);
    }
}