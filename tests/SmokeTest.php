<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\NestedFlowTracker;
use Illuminate\Support\Facades\Schema;

class SmokeTest extends TestCase
{
    public function test_migration_creates_the_tracks_table(): void
    {
        $this->assertTrue(Schema::hasTable('fn_flow_tracks'));
    }

    public function test_start_and_end_track_persists_a_record(): void
    {
        $timer = 'smoke-timer';

        NestedFlowTracker::startTrack($timer, 'smoke message');
        NestedFlowTracker::endTrack($timer);

        $this->assertDatabaseHas('fn_flow_tracks', [
            'message' => 'smoke message',
            'component' => 'test-component',
        ]);
    }
}
