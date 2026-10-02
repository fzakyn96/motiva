<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_is_sql_server()
    {
        $connection = DB::connection()->getDriverName();
        $this->assertEquals('sqlsrv', $connection, 'Database driver must be sqlsrv');

        $result = DB::select('SELECT 1 as val');
        $this->assertEquals(1, $result[0]->val, 'Database must return result from simple query');
    }

    public function test_redis_connection_is_working()
    {
        Cache::store('redis')->put('test_key', 'test_value', 10);
        $value = Cache::store('redis')->get('test_key');
        
        $this->assertEquals('test_value', $value, 'Redis cache must store and retrieve values');
    }

    public function test_queue_connection_is_redis()
    {
        $connection = config('queue.default');
        $this->assertEquals('redis', $connection, 'Default queue connection must be redis');
    }
}
