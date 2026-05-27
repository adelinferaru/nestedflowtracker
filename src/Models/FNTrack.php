<?php

namespace AdelinFeraru\NestedFlowTracker\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property string $tracker_id
 * @property int|null $user_id
 * @property string $component
 * @property string|null $message
 * @property float|null $duration
 * @property string|null $context
 * @property string|null $result
 * @property int|null $parent_id
 * @property int $_lft
 * @property int $_rgt
 */
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

    public function getParentId(): ?int
    {
        return $this->parent_id;
    }

    /**
     * @param int|string|null $user_id
     */
    public function setUserId($user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    /**
     * @param int|float|string $tracker_id
     */
    public function setTrackerId($tracker_id): static
    {
        $this->tracker_id = $tracker_id;

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context = []): static
    {
        $this->context = !empty($context) ? json_encode($context) : null;

        return $this;
    }

    public function setDuration(?float $duration = null): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function setTrackParent(FNTrack $parent): static
    {
        $this->appendToNode($parent);

        return $this;
    }
}
