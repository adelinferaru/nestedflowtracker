<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

class NestedFlowTracker
{
    /** @var int|float|string|null The id grouping every track of the current flow. */
    protected static $tracker_id = null;

    /** @var int|string|null The user the flow is performed by/for. */
    protected static $user_id = null;

    /** @var array<string, float> Start timestamps keyed by timer name. */
    protected static array $timers = [];

    /** @var list<FNTrack> Stack of currently open tracks (innermost last). */
    protected static array $tracks_queue = [];

    protected static ?string $db_connection = null;

    /**
     * @return int|float|string|null
     */
    public static function getTrackerId()
    {
        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            return self::$tracker_id;
        }

        return null;
    }

    /**
     * @param int|float|string|null $tracker_id A null value generates a new id.
     */
    public static function setTrackerId($tracker_id = null): void
    {
        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            if (is_null($tracker_id)) {
                $tracker_id = hexdec(uniqid());
            }

            self::$tracker_id = $tracker_id;
            session(['tracker_id' => self::$tracker_id]);
        }
    }

    /**
     * @param int|string|null $user_id
     */
    public static function setUserId($user_id): void
    {
        self::$user_id = $user_id;
    }

    public static function getDBConnection(): string
    {
        if (self::$db_connection === null) {
            $connection = config('nestedflowtracker.db_connection');

            self::$db_connection = $connection === 'default'
                ? (string) config('database.default')
                : (string) $connection;
        }

        return (string) self::$db_connection;
    }

    /**
     * Start a (sub-)flow timer and create its tracking record.
     *
     * @param string $trackerName Unique timer name; also used by endTrack to read the duration.
     * @param string|array<mixed>|null $message Optional message (arrays are JSON-encoded).
     * @param array<string, mixed> $settings Optional overrides: tracker_id, user_id, component,
     *                        message, result, context, parent_id, root.
     * @return FNTrack|false The created record, or false when tracking is disabled.
     */
    public static function startTrack(string $trackerName, $message = null, array $settings = [])
    {
        if (config('nestedflowtracker.flow_tracker_active') != 1) {
            return false;
        }

        // Start the timer.
        self::startTimer($trackerName);

        $tracker = new FNTrack();
        $tracker->setConnection(self::getDBConnection());

        // Resolve the flow's tracker_id: an explicit one from settings wins; otherwise
        // continue the current flow (static, then session), or start a brand new one.
        if (!empty($settings['tracker_id'])) {
            self::setTrackerId($settings['tracker_id']);
        } elseif (!self::$tracker_id) {
            self::setTrackerId(session('tracker_id') ?: hexdec(uniqid()));
        }
        $tracker->tracker_id = self::$tracker_id;

        $tracker->component = $settings['component'] ?? config('nestedflowtracker.component');

        // Carry the user across the flow, allowing a per-track override.
        if (isset($settings['user_id'])) {
            self::$user_id = $settings['user_id'];
        }
        if (self::$user_id !== null) {
            $tracker->user_id = self::$user_id;
        }

        // Message: explicit argument, then settings, then the timer name.
        if ($message !== null) {
            $tracker->message = is_array($message) ? json_encode($message) : $message;
        } elseif (isset($settings['message'])) {
            $tracker->message = is_array($settings['message']) ? json_encode($settings['message']) : $settings['message'];
        } else {
            $tracker->message = $trackerName;
        }

        if (isset($settings['result'])) {
            $tracker->result = is_array($settings['result']) ? json_encode($settings['result']) : $settings['result'];
        }

        if (isset($settings['context'])) {
            $tracker->context = is_array($settings['context']) ? json_encode($settings['context']) : $settings['context'];
        }

        // Unless explicitly flagged as a root, nest this track under the currently
        // open one (the last entry on the stack), inheriting its tracker_id.
        if (empty($settings['root']) && count(self::$tracks_queue) > 0) {
            $parentTracker = end(self::$tracks_queue);
            $tracker->appendToNode($parentTracker);
            $tracker->tracker_id = $parentTracker->tracker_id;
        }

        // Or attach to an explicitly provided parent.
        if (!empty($settings['parent_id'])) {
            $tracker->parent_id = $settings['parent_id'];
        }

        $tracker->save();

        // Push onto the open-tracks stack.
        self::$tracks_queue[] = $tracker;

        return $tracker;
    }

    /**
     * Set the parent of the currently open track.
     *
     * @param int|string $parent_id
     * @param bool $save True to persist the change immediately.
     */
    public static function setParentId($parent_id, bool $save = false): void
    {
        $tracker = end(self::$tracks_queue);

        if ($tracker === false) {
            return;
        }

        $tracker->parent_id = $parent_id;

        if ($save) {
            $tracker->save();
        }
    }

    /**
     * Start (or restart) a named timer.
     */
    public static function startTimer(string $timerName): void
    {
        self::$timers[$timerName] = microtime(true);
    }

    /**
     * Elapsed seconds since the named timer was started.
     */
    public static function getTimerDuration(string $timerName): float
    {
        $start = self::$timers[$timerName] ?? microtime(true);

        return microtime(true) - $start;
    }

    /**
     * Close the most recently opened track and record its duration.
     *
     * Contract: tracks are closed in LIFO order. $trackerName is used only to read this
     * track's timer (its duration); it does NOT select which track is closed — the track on
     * top of the stack is always the one closed. Callers must therefore balance start/end
     * calls (last opened is first closed). Calling endTrack on an empty stack is a no-op.
     *
     * You may update message, user_id, context, result, tracker_id at this stage via $settings
     * (an array of overrides). A bare non-empty scalar $settings is stored as the result.
     *
     * @param string $trackerName Name of the timer started via startTrack.
     * @param array<string, mixed>|string|null $settings Field overrides, or a scalar result.
     */
    public static function endTrack(string $trackerName, $settings = null): void
    {
        if (config('nestedflowtracker.flow_tracker_active') != 1) {
            return;
        }

        if (count(self::$tracks_queue) === 0) {
            return;
        }

        $tracker = array_pop(self::$tracks_queue);
        $tracker->duration = self::getTimerDuration($trackerName);

        if (is_array($settings)) {
            if (isset($settings['message'])) {
                $tracker->message = is_array($settings['message']) ? json_encode($settings['message']) : $settings['message'];
            }

            if (isset($settings['result'])) {
                $tracker->result = is_array($settings['result']) ? json_encode($settings['result']) : $settings['result'];
            }

            if (isset($settings['context'])) {
                $tracker->context = is_array($settings['context']) ? json_encode($settings['context']) : $settings['context'];
            }

            if (isset($settings['user_id'])) {
                $tracker->user_id = $settings['user_id'];
                self::$user_id = $settings['user_id'];
            }

            if (isset($settings['tracker_id'])) {
                $tracker->tracker_id = $settings['tracker_id'];
                self::$tracker_id = $settings['tracker_id'];
            }
        } elseif (is_scalar($settings) && trim((string) $settings) !== '') {
            // A bare scalar is stored as the result.
            $tracker->result = $settings;
        }

        $tracker->save();
    }
}
