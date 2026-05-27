<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\NestedFlowTracker;
use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

/**
 * Characterization tests for the flow_tracker_active master switch.
 * These pin CURRENT behavior (Phase 1), quirks included.
 */
class ActiveSwitchTest extends TestCase
{
    private function deactivate(): void
    {
        config(['nestedflowtracker.flow_tracker_active' => 0]);
    }

    public function test_inactive_get_tracker_id_returns_null(): void
    {
        $this->deactivate();

        $this->assertNull(NestedFlowTracker::getTrackerId());
    }

    public function test_inactive_start_track_returns_false_and_writes_nothing(): void
    {
        $this->deactivate();

        $result = NestedFlowTracker::startTrack('timer', 'message');

        $this->assertFalse($result);
        $this->assertSame(0, FNTrack::query()->count());
    }

    public function test_inactive_end_track_is_a_noop(): void
    {
        $this->deactivate();

        // Should not throw even though nothing was started.
        NestedFlowTracker::endTrack('timer');

        $this->assertSame(0, FNTrack::query()->count());
    }

    public function test_inactive_set_tracker_id_does_not_persist(): void
    {
        $this->deactivate();

        NestedFlowTracker::setTrackerId('abc123');

        $this->assertNull(NestedFlowTracker::getTrackerId());
        $this->assertNull(session('tracker_id'));
    }

    public function test_active_start_track_returns_model_and_writes_a_row(): void
    {
        // Active by default in the test environment.
        $track = NestedFlowTracker::startTrack('timer', 'message');

        $this->assertInstanceOf(FNTrack::class, $track);
        $this->assertSame(1, FNTrack::query()->count());
    }
}
