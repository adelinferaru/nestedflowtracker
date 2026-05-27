<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\NestedFlowTracker;
use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

/**
 * Characterization tests for tracker_id generation and propagation (Phase 1).
 */
class TrackerIdTest extends TestCase
{
    public function test_start_track_generates_and_exposes_a_tracker_id(): void
    {
        $track = NestedFlowTracker::startTrack('timer');

        $id = NestedFlowTracker::getTrackerId();
        $this->assertNotNull($id);
        $this->assertSame((string) $id, (string) $track->tracker_id);
    }

    public function test_generated_tracker_id_is_persisted_to_the_session(): void
    {
        NestedFlowTracker::startTrack('timer');

        $this->assertSame(
            (string) NestedFlowTracker::getTrackerId(),
            (string) session('tracker_id')
        );
    }

    public function test_sibling_root_tracks_share_the_same_tracker_id(): void
    {
        NestedFlowTracker::startTrack('a');
        NestedFlowTracker::endTrack('a');

        NestedFlowTracker::startTrack('b');
        NestedFlowTracker::endTrack('b');

        $ids = FNTrack::query()->pluck('tracker_id')->unique();
        $this->assertCount(1, $ids);
    }

    public function test_explicit_tracker_id_in_settings_is_honored(): void
    {
        $track = NestedFlowTracker::startTrack('timer', null, ['tracker_id' => 'flow-xyz']);

        $this->assertSame('flow-xyz', (string) $track->tracker_id);
        $this->assertSame('flow-xyz', (string) NestedFlowTracker::getTrackerId());
    }

    public function test_set_tracker_id_is_used_by_a_following_start_track(): void
    {
        NestedFlowTracker::setTrackerId('preset-id');

        $track = NestedFlowTracker::startTrack('timer');

        $this->assertSame('preset-id', (string) $track->tracker_id);
    }
}
