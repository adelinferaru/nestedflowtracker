<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\NestedFlowTracker;
use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

/**
 * Characterization tests for the nested-set tree building driven by the
 * start/end stack (Phase 1).
 */
class NestingTest extends TestCase
{
    public function test_a_track_started_inside_another_becomes_its_child(): void
    {
        $root = NestedFlowTracker::startTrack('root');
        $child = NestedFlowTracker::startTrack('child');
        NestedFlowTracker::endTrack('child');
        NestedFlowTracker::endTrack('root');

        $this->assertSame($root->id, FNTrack::find($child->id)->parent_id);
    }

    public function test_a_child_inherits_the_parent_tracker_id(): void
    {
        $root = NestedFlowTracker::startTrack('root', null, ['tracker_id' => 'parent-flow']);
        $child = NestedFlowTracker::startTrack('child');
        NestedFlowTracker::endTrack('child');
        NestedFlowTracker::endTrack('root');

        $this->assertSame('parent-flow', (string) FNTrack::find($child->id)->tracker_id);
    }

    public function test_sequential_children_become_siblings_under_the_same_root(): void
    {
        $root = NestedFlowTracker::startTrack('root');

        $a = NestedFlowTracker::startTrack('a');
        NestedFlowTracker::endTrack('a');

        $b = NestedFlowTracker::startTrack('b');
        NestedFlowTracker::endTrack('b');

        NestedFlowTracker::endTrack('root');

        $this->assertSame($root->id, FNTrack::find($a->id)->parent_id);
        $this->assertSame($root->id, FNTrack::find($b->id)->parent_id);
        $this->assertSame(2, FNTrack::find($root->id)->children()->count());
    }

    public function test_three_levels_of_nesting_chain_correctly(): void
    {
        $root = NestedFlowTracker::startTrack('root');
        $c1 = NestedFlowTracker::startTrack('c1');
        $c11 = NestedFlowTracker::startTrack('c11');
        NestedFlowTracker::endTrack('c11');
        NestedFlowTracker::endTrack('c1');
        NestedFlowTracker::endTrack('root');

        $this->assertSame($root->id, FNTrack::find($c1->id)->parent_id);
        $this->assertSame($c1->id, FNTrack::find($c11->id)->parent_id);
        // The whole chain hangs under the root.
        $this->assertSame(2, FNTrack::find($root->id)->descendants()->count());
    }

    public function test_root_flag_forces_an_independent_root_even_inside_another_track(): void
    {
        NestedFlowTracker::startTrack('root');
        $independent = NestedFlowTracker::startTrack('independent', null, ['root' => true]);

        $this->assertNull(FNTrack::find($independent->id)->parent_id);
    }

    public function test_explicit_parent_id_attaches_to_that_parent(): void
    {
        $other = NestedFlowTracker::startTrack('other');
        NestedFlowTracker::endTrack('other');

        $track = NestedFlowTracker::startTrack('child', null, ['parent_id' => $other->id]);
        NestedFlowTracker::endTrack('child');

        $this->assertSame($other->id, FNTrack::find($track->id)->parent_id);
    }
}
