<?php

namespace AdelinFeraru\NestedFlowTracker;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class FNTrack extends Model
{
    use NodeTrait;

    protected $table = 'fn_flow_tracks';
    protected $fillable = [
        'tracker_id',
        'user_id',
        'component',
        'message',
        'context',
        'result',
        'parent_id'
    ];

    public function getParentId() {
        return $this->parent_id;
    }

    /**
     * @param $user_id
     * @return $this
     */
    public function setUserId($user_id) {
        $this->user_id = $user_id;
        return $this;
    }

    /**
     * @param $tracker_id
     * @return $this
     */
    public function setTrackerId($tracker_id) {
        $this->tracker_id = $tracker_id;
        return $this;
    }

    /**
     * @param array $context
     * @return $this
     */
    public function setContext($context = []) {
        $this->context = !empty($context) && is_array($context) ? json_encode($context) : null;
        return $this;
    }

    /**
     * @param null $duration
     * @return $this
     */
    public function setDuration($duration = null) {
        $this->duration = $duration;
        return $this;
    }

    public function setTrackParent(FNTrack $parent)
    {
        $this->appendToNode($parent);
        return $this;
    }
}
