<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\NestedFlowTracker;
use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

/**
 * Characterization tests for settings resolution (start/end), duration capture,
 * and endTrack stack behavior (Phase 1).
 */
class SettingsDurationTest extends TestCase
{
    // --- message resolution on startTrack ---

    public function test_message_defaults_to_the_tracker_name(): void
    {
        $track = NestedFlowTracker::startTrack('my-timer');

        $this->assertSame('my-timer', $track->message);
    }

    public function test_message_argument_wins(): void
    {
        $track = NestedFlowTracker::startTrack('my-timer', 'explicit message');

        $this->assertSame('explicit message', $track->message);
    }

    public function test_array_message_argument_is_json_encoded(): void
    {
        $track = NestedFlowTracker::startTrack('my-timer', ['k' => 'v']);

        $this->assertSame('{"k":"v"}', $track->message);
    }

    public function test_message_falls_back_to_settings_message_when_argument_is_null(): void
    {
        $track = NestedFlowTracker::startTrack('my-timer', null, ['message' => 'from settings']);

        $this->assertSame('from settings', $track->message);
    }

    // --- other startTrack settings ---

    public function test_component_defaults_to_config_and_can_be_overridden(): void
    {
        $default = NestedFlowTracker::startTrack('a');
        NestedFlowTracker::endTrack('a');
        $custom = NestedFlowTracker::startTrack('b', null, ['component' => 'custom-component']);

        $this->assertSame('test-component', $default->component);
        $this->assertSame('custom-component', $custom->component);
    }

    public function test_array_result_and_context_are_json_encoded(): void
    {
        $track = NestedFlowTracker::startTrack('a', null, [
            'result' => ['ok' => 1],
            'context' => ['foo' => 'bar'],
        ]);

        $this->assertSame('{"ok":1}', $track->result);
        $this->assertSame('{"foo":"bar"}', $track->context);
    }

    public function test_user_id_from_settings_is_stored(): void
    {
        $track = NestedFlowTracker::startTrack('a', null, ['user_id' => 42]);

        $this->assertSame(42, (int) FNTrack::find($track->id)->user_id);
    }

    // --- duration + endTrack ---

    public function test_matched_end_track_records_a_duration(): void
    {
        $track = NestedFlowTracker::startTrack('a');
        NestedFlowTracker::endTrack('a');

        $duration = FNTrack::find($track->id)->duration;
        $this->assertNotNull($duration);
        $this->assertGreaterThanOrEqual(0, (float) $duration);
    }

    public function test_end_track_scalar_settings_becomes_the_result(): void
    {
        $track = NestedFlowTracker::startTrack('a');
        NestedFlowTracker::endTrack('a', 'plain result');

        $this->assertSame('plain result', FNTrack::find($track->id)->result);
    }

    public function test_end_track_array_settings_update_fields(): void
    {
        $track = NestedFlowTracker::startTrack('a', 'start message');
        NestedFlowTracker::endTrack('a', [
            'message' => 'end message',
            'result' => ['done' => true],
        ]);

        $reloaded = FNTrack::find($track->id);
        $this->assertSame('end message', $reloaded->message);
        $this->assertSame('{"done":true}', $reloaded->result);
    }

    public function test_end_track_on_empty_stack_is_a_noop(): void
    {
        NestedFlowTracker::endTrack('never-started');

        $this->assertSame(0, FNTrack::query()->count());
    }

    /**
     * QUIRK (to be addressed in a later phase): endTrack pops the most recently
     * started track regardless of the name passed. The name is only used to look
     * up the timer for the duration. So ending by a stale name closes the wrong
     * track.
     */
    public function test_end_track_pops_lifo_ignoring_the_name_argument(): void
    {
        $a = NestedFlowTracker::startTrack('a');
        $b = NestedFlowTracker::startTrack('b');

        // Intentionally end by the OUTER name while 'b' is on top of the stack.
        NestedFlowTracker::endTrack('a');

        // 'b' (the top of the stack) was closed, not 'a'.
        $this->assertNotNull(FNTrack::find($b->id)->duration);
        $this->assertNull(FNTrack::find($a->id)->duration);
    }
}
