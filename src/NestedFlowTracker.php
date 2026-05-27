<?php

namespace AdelinFeraru\NestedFlowTracker;

use AdelinFeraru\NestedFlowTracker\Models\FNTrack;

class NestedFlowTracker
{
    protected static $instance = null;
    protected static $tracker_id = null;
    protected static $user_id = null;
    protected static $timers = [];
    protected static $tracks_queue = [];
    protected static $db_connection = null;

    public function __construct()
    {
        //
    }

    private function __clone()
    {
        //
    }

    public function __wakeup()
    {
        //
    }


    /**
     * @return float|int|null
     */
    public static function getTrackerId(){
        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            return self::$tracker_id;
        }
        return null;
    }

    /**
     * @param int|float|string|null $tracker_id A null value generates a new id.
     * @return void
     */
    public static function setTrackerId($tracker_id = null){
        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            if (is_null($tracker_id)) $tracker_id = hexdec(uniqid());

            self::$tracker_id = $tracker_id;
            session(['tracker_id' => self::$tracker_id]);
        }
    }

    /**
     * @param $user_id
     */
    public static function setUserId($user_id) {
        self::$user_id = $user_id;
    }

    /**
     * @return NestedFlowTracker
     */
    public static function getInstance() {
        if (is_null(static::$instance)) {
            static::$instance = new self();
        }

        return static::$instance;
    }


    public static function getDBConnection() {
        if(self::$db_connection == null) {
            $db_connection = config('nestedflowtracker.db_connection');
            if($db_connection == "default") {
                self::$db_connection = \Config::get('database.default');
            }
            else {
                self::$db_connection = $db_connection;
            }
        }

        return self::$db_connection;
    }


    /**
     * Start a (sub-)flow timer and create its tracking record.
     *
     * @param string $trackerName Unique timer name; also used by endTrack to read the duration.
     * @param string|array|null $message Optional message (arrays are JSON-encoded).
     * @param array $settings Optional overrides: tracker_id, user_id, component, message,
     *                        result, context, parent_id, root.
     * @return FNTrack|false The created record, or false when tracking is disabled.
     */
    public static function startTrack($trackerName, $message = null, $settings = []) {

        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            // Start the timer
            self::startTimer($trackerName);

            // Get Database connection
            $db_connection = self::getDBConnection();

            // Create a FNTrack instance
            $tracker = new FNTrack();
            $tracker->setConnection($db_connection);

            // Resolve the flow's tracker_id: an explicit one from settings wins; otherwise
            // continue the current flow (static, then session), or start a brand new one.
            if (!empty($settings['tracker_id'])) {
                self::setTrackerId($settings['tracker_id']);
            } elseif (!self::$tracker_id) {
                self::setTrackerId(session('tracker_id') ?: hexdec(uniqid()));
            }
            $tracker->tracker_id = self::$tracker_id;

            // Set the component name
            $tracker->component = $settings['component'] ?? config('nestedflowtracker.component');

            // Set the user_id of exists
            if (isset($settings['user_id'])) {
                $tracker->user_id = $settings['user_id'];
                self::$user_id = $settings['user_id'];
            } elseif (self::$user_id !== null) {
                $tracker->user_id = self::$user_id;
            }

            // Set a message if exists
            if ($message !== null) {
                $tracker->message = is_array($message) ? json_encode($message) : $message;
            } else {
                if (isset($settings['message'])) {
                    $tracker->message = is_array($settings['message']) ? json_encode($settings['message']) : $settings['message'];
                } else {
                    $tracker->message = $trackerName;
                }
            }


            // Set a result if exists
            if (isset($settings['result'])) {
                $tracker->result = is_array($settings['result']) ? json_encode($settings['result']) : $settings['result'];
            }

            // Add context if exists
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


            // Add this track to the queue
            self::$tracks_queue [] = $tracker;

            return $tracker;
        }
         return false;
    }

    /**
     * @param $parent_id
     * @param bool $save - True to save the model. Default is False.
     */
    public static function setParentId($parent_id, $save = false) {
        $tracker = end(self::$tracks_queue);
        $tracker->parent_id = $parent_id;

        if($save) $tracker->save();
    }


    /**
     * Add a named timer to the static $timers property
     * @param $timerName
     */
    public static function startTimer($timerName) {
        self::$timers[$timerName] = microtime(true);
    }

    /**
     * @param $timerName
     * @return float|mixed|string
     */
    public static function getTimerDuration($timerName) {
        return microtime(true) - self::$timers[$timerName];
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
     * @param array|string|null $settings Field overrides, or a scalar result.
     */
    public static function endTrack($trackerName, $settings = null) {
        if (config('nestedflowtracker.flow_tracker_active') == 1) {
            if (count(self::$tracks_queue) > 0) {
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
    }
}
