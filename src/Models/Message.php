<?php

namespace Cmgmyr\Messenger\Models;

use Cmgmyr\Messenger\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Eloquent
{
    use BelongsToUser,
        SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'messages';

    /**
     * The relationships that should be touched on save.
     *
     * @var array
     */
    protected $touches = ['thread'];

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['thread_id', 'user_type', 'user_id', 'body'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('messages');

        parent::__construct($attributes);
    }

    /**
     * Thread relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @codeCoverageIgnore
     */
    public function thread()
    {
        return $this->belongsTo(Models::classname(Thread::class), 'thread_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function participant()
    {
        return $this->belongsTo(Models::classname(Participant::class));
    }

    /**
     * Participants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function participants()
    {
        return $this->hasMany(Models::classname(Participant::class), 'thread_id', 'thread_id');
    }

    /**
     * Recipients of this message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recipients()
    {
        return $this->participants()->exceptForUser($this->user_id, $this->user_type);
    }

    /**
     * Returns unread messages given the userId.
     *
     * @param Builder $query
     * @param $userId
     * @return Builder
     */
    public function scopeUnreadForUser(Builder $query, $userId, $userType = null)
    {
        return $query
            ->exceptForUser($userId, $userType)
            ->whereHas('participants', function (Builder $query) use ($userId, $userType) {
                $query->forUser($userId, $userType)
                    ->where(function (Builder $q) {
                        $q->whereRaw('last_read < '.$this->getTable().'.created_at')->orWhereNull('last_read');
                    });
            });
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
            'participant_id' => Thread::findOrFail($this->thread_id)->addParticipant($user)->first()->id
        ]);
    }
}
