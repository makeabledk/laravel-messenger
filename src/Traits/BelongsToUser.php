<?php

namespace Cmgmyr\Messenger\Traits;

use Cmgmyr\Messenger\Models\Participant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToUser
{
    use NormalizesMorphs;

    /**
     * User relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     *
     * @codeCoverageIgnore
     */
    public function user()
    {
        return $this->morphTo();
    }

    /**
     * Returns unread messages given the userId.
     *
     * @param Builder $query
     * @param $userId
     * @param $userType
     * @return Builder
     */
    public function scopeForUser(Builder $query, $userId, $userType = null)
    {
        list($id, $type) = $this->getMorphIdAndType($userId, $userType);

        return $query->where('user_id', $id)->where('user_type', $type);
    }

    /**
     * Returns unread messages given the userId.
     *
     * @param Builder $query
     * @param $userId
     * @param $userType
     * @return Builder
     */
    public function scopeExceptForUser(Builder $query, $userId, $userType = null)
    {
        list($id, $type) = $this->getMorphIdAndType($userId, $userType);

        return $query->where('user_id', '!=', $id)->orWhere('user_type', '!=', $type);
    }

    /**
     * @param $user
     * @return mixed
     */
    public function setUser($user)
    {
        return $this->fill([
            'user_id' => $user->getKey(),
            'user_type' => $user->getMorphClass(),
        ]);
    }
}
