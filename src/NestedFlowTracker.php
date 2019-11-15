<?php

namespace AdelinFeraru\NestedFlowTracker;

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

    private function __wakeup()
    {
        //
    }


    /**
     * @return float|int|null
     */
    public static function getTrackerId(){
        return self::$tracker_id;
    }

    /**
     * @param null $tracker_id
     * @return void
     */
    public static function setTrackerId($tracker_id = null){
        if(is_null($tracker_id)) $tracker_id = hexdec(uniqid());

        self::$tracker_id = $tracker_id;
        session(['tracker_id' => self::$tracker_id]);

        /*return static::$instance;*/
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
            static::$instance = new static();

        }

        return static::$instance;
    }


    /**
     * @param $message
     * @param null $parent_id
     * @param array $settings
     * @return FNTrack
     */
    public static function startTrack($trackerName, $message = null, $settings = []) {

        // Start the timer
        self::startTimer($trackerName);

        // Get Database connection
        if(self::$db_connection == null) {
            $db_connection = config('nestedflowtracker.db_connection');
            if($db_connection == "default") {
                self::$db_connection = \Config::get('database.default');
            }
            else {
                self::$db_connection = $db_connection;
            }
        }


        // Create a FNTrack instance
        $tracker = new FNTrack();
        $tracker->setConnection(self::$db_connection);

        // Try to set Tracker_ID
        if(isset($settings['tracker_id']) && !empty($settings['tracker_id'])) {
            $tracker->tracker_id = $settings['tracker_id'];
            self::setTrackerId($settings['tracker_id']);
        }
        else {
            $tracker->tracker_id = self::$tracker_id ? self::$tracker_id : (session('tracker_id') ? session('tracker_id') : hexdec(uniqid()));
        }


        // Set the tracker's description/message
        $tracker->message = $message;

        // Set the component name
        $tracker->component = $settings['component'] ?? config('nestedflowtracker.component');

        // Set the user_id of exists
        if(isset($settings['user_id'])) {
            $tracker->user_id = $settings['user_id'];
            self::$user_id = $settings['user_id'];
        }
        elseif (self::$user_id !== null) {
            $tracker->user_id = self::$user_id;
        }

        // Set a message if exists
        if($message !== null && trim($message) != "") {
            $tracker->message = is_array($message) ? json_encode($message) : $message;
        }
        else {
            if (isset($settings['message'])) {
                $tracker->message = is_array($settings['message']) ? json_encode($settings['message']) : $settings['message'];
            }
            else {
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

        if(!isset($settings['root']) || empty($settings['root']) || !$settings['root']) {
            // Add this track as the child of the last track from tracks_queue
            if(count(self::$tracks_queue) > 0) {
                $parentTracker = end(self::$tracks_queue);
                $tracker->appendToNode($parentTracker);
                $tracker->tracker_id = $parentTracker->tracker_id;
            }
        }

        /*if(! is_null($parent_id)) {
            $tracker->parent_id = $parent_id;
        }*/

        $tracker->save();

        // Add this track to the queue
        self::$tracks_queue [] = $tracker;

        return $tracker;
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
     * End the tracker that was started with the specified $trackerName.
     * You may update message, user_id, context, result, tracker_id at this stage by
     * filling the settings parameter
     * @param $trackerName
     * @param $settings
     */
    public static function endTrack($trackerName, $settings = null) {
        if( count(self::$tracks_queue) > 0) {
            $tracker = array_pop(self::$tracks_queue);
            $tracker->duration = self::getTimerDuration($trackerName);

            if($settings !== null) {
                if(!is_array($settings) && trim($settings) != "") {
                    $tracker->result = $settings;
                }
                else {

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
                }
            }

            $tracker->save();
        }


    }
}