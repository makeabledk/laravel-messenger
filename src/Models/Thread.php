<?php

namespace Cmgmyr\Messenger\Models;

use Carbon\Carbon;
use Cmgmyr\Messenger\Traits\NormalizesMorphs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Thread extends Eloquent
{
    use NormalizesMorphs,
        SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'threads';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['name', 'subject_id', 'subject_type'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    
    /**
    * Internal cache for creator.
    */
    protected $creatorCache = false;
    
    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('threads');

        parent::__construct($attributes);
    }

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function messages()
    {
        return $this->hasMany(Models::classname(Message::class), 'thread_id', 'id');
    }

    /**
     * Returns the latest message from a thread.
     *
     * @return \Cmgmyr\Messenger\Models\Message
     */
    public function getLatestMessageAttribute()
    {
        return $this->messages()->latest()->first();
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
        return $this->hasMany(Models::classname(Participant::class), 'thread_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * User's relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     *
     * @codeCoverageIgnore
     */
    public function users($class)
    {
        return $this->morphedByMany($class, 'user', Models::table('participants'), 'thread_id');
    }

    /**
     * Returns the user object that created the thread.
     *
     * @return Eloquent | null
     */
    public function creator()
    {
        if ($this->creatorCache === false) {
            $this->creatorCache = optional($this->messages()->withTrashed()->oldest()->first())->user;
        }

        return $this->creatorCache;
    }

    /**
     * Returns all of the latest threads by updated_at date.
     *
     * @return self
     */
    public static function getAllLatest()
    {
        return self::latest('updated_at');
    }

    /**
     * Returns all threads by subject.
     *
     * @param string $name
     * @return self
     */
    public static function getByName($name)
    {
        return self::where('name', 'like', $name)->get();
    }

    /**
     * Returns threads that the user is associated with.
     *
     * @param Builder $query
     * @param $userId
     * @param null $userType
     *
     * @return Builder
     */
    public function scopeForUser(Builder $query, $userId, $userType = null)
    {
        $participantsTable = Models::table('participants');
        $threadsTable = Models::table('threads');
        list($userId, $userType) = $this->getMorphIdAndType($userId, $userType);

        return $query->join($participantsTable, $this->getQualifiedKeyName(), '=', $participantsTable . '.thread_id')
            ->where($participantsTable . '.user_type', $userType)
            ->where($participantsTable . '.user_id', $userId)
            ->where($participantsTable . '.deleted_at', null)
            ->select($threadsTable . '.*');
    }

    /**
     * @param $query
     * @param $subject
     * @return mixed
     */
    public function scopeForSubject($query, $subject)
    {
        return $query
            ->where('subject_id', $subject->getKey())
            ->where('subject_type', $subject->getMorphClass());
    }

    /**
     * Returns threads with new messages that the user is associated with.
     *
     * @param Builder $query
     * @param $userId
     * @param null $userType
     *
     * @return Builder
     */
    public function scopeForUserWithNewMessages(Builder $query, $userId, $userType = null)
    {
        $participantTable = Models::table('participants');
        $threadsTable = Models::table('threads');
        list($userId, $userType) = $this->getMorphIdAndType($userId, $userType);

        return $query->join($participantTable, $this->getQualifiedKeyName(), '=', $participantTable . '.thread_id')
            ->where($participantTable . '.user_type', $userType)
            ->where($participantTable . '.user_id', $userId)
            ->whereNull($participantTable . '.deleted_at')
            ->where(function (Builder $query) use ($participantTable, $threadsTable) {
                $query->where($threadsTable . '.updated_at', '>', $this->getConnection()->raw($this->getConnection()->getTablePrefix() . $participantTable . '.last_read'))
                    ->orWhereNull($participantTable . '.last_read');
            })
            ->select($threadsTable . '.*');
    }

    /**
     * Returns threads between given user ids.
     *
     * @param Builder $query
     * @param array $users
     *
     * @return Builder
     */
    public function scopeBetween(Builder $query, array $users)
    {
        return $query->whereHas('participants', function (Builder $q) use ($users) {
            $q->where(function ($q) use ($users) {
                foreach ($users as $user) {
                    $q->orWhere(function ($q) use ($user) {
                        $q->forUser($user);
                    });
                }
            })->select($this->getConnection()->raw('DISTINCT(thread_id)'))
                ->groupBy('thread_id')
                ->havingRaw('COUNT(thread_id)=' . count($users));
        });
    }

    /**
     * Add users to thread as participants.
     *
     * @param array|mixed $user
     */
    public function addParticipant($user)
    {
        Collection::wrap(count(func_get_args()) > 1 ? func_get_args() : $user)
            ->each(function ($user) {
                Models::participant()->firstOrCreate([
                    'user_type' => $user->getMorphClass(),
                    'user_id' => $user->getKey(),
                    'thread_id' => $this->id,
                ]);
            });
    }

    /**
     * Remove participants from thread.
     *
     * @param array|mixed $user
     */
    public function removeParticipant($user)
    {
        $users = is_array($user) ? $user : (array) func_get_args();

        collect($users)->each(function ($user) {
            Models::participant()->where('thread_id', $this->id)->forUser($user)->delete();
        });
    }

    /**
     * Mark a thread as read for a user.
     *
     * @param int $user
     */
    public function markAsRead($user)
    {
        try {
            $participant = $this->getParticipantFromUser($user);
            $participant->last_read = new Carbon();
            $participant->save();
        } catch (ModelNotFoundException $e) { // @codeCoverageIgnore
            // do nothing
        }
    }

    /**
     * See if the current thread is unread by the user.
     *
     * @param int $user
     *
     * @return bool
     */
    public function isUnread($user)
    {
        try {
            $participant = $this->getParticipantFromUser($user);

            if ($participant->last_read === null || $this->updated_at->gt($participant->last_read)) {
                return true;
            }
        } catch (ModelNotFoundException $e) { // @codeCoverageIgnore
            // do nothing
        }

        return false;
    }

    /**
     * Finds the participant record from a user id.
     *
     * @param $userId
     * @param $userType
     *
     * @return mixed
     */
    public function getParticipantFromUser($userId, $userType = null)
    {
        return $this->participants()->forUser($userId, $userType)->firstOrFail();
    }

    /**
     * Restores all participants within a thread that has a new message.
     */
    public function activateAllParticipants()
    {
        $participants = $this->participants()->withTrashed()->get();
        foreach ($participants as $participant) {
            $participant->restore();
        }
    }

    /**
     * Generates a string of participant information.
     *
     * @param null  $user
     * @param array $columns
     *
     * @return string
     */
    public function participantsString($user = null, $columns = null)
    {
        return $this->participants()
            ->when($user !== null, function ($q) use ($user) {
                $q->exceptForUser($user);
            })
            ->with('user')
            ->get()
            ->map(function ($participant) use ($columns) {
                return $this->stringifyParticipant($participant, $columns);
            })
            ->implode(', ');
    }

    /**
     * Checks to see if a user is a current participant of the thread.
     *
     * @param $userId
     * @param null $userType
     *
     * @return bool
     */
    public function hasParticipant($userId, $userType = null)
    {
        return $this->participants()->forUser($userId, $userType)->exists();
    }

    /**
     * Generates a select string used in participantsString().
     *
     * @param $participant
     * @param null $columns
     *
     * @return string
     */
    protected function stringifyParticipant($participant, $columns = null)
    {
        list($name, $columns) = ['', $columns ?: ['name']];

        foreach ($columns as $column) {
            $name .= $participant->user->$column;
        }

        return trim($name, ' ');
    }

    /**
     * Returns array of unread messages in thread for given user.
     *
     * @param $userId
     *
     * @param null $userType
     *
     * @return \Illuminate\Support\Collection
     */
    public function userUnreadMessages($userId, $userType = null)
    {
        $messages = $this->messages()->get();

        try {
            $participant = $this->getParticipantFromUser($userId, $userType);
        } catch (ModelNotFoundException $e) {
            return collect();
        }

        if (! $participant->last_read) {
            return $messages;
        }

        return $messages->filter(function ($message) use ($participant) {
            return $message->updated_at->gt($participant->last_read);
        });
    }

    /**
     * Returns count of unread messages in thread for given user.
     *
     * @param $userId
     * @param null $userType
     *
     * @return int
     */
    public function userUnreadMessagesCount($userId, $userType = null)
    {
        return $this->userUnreadMessages($userId, $userType)->count();
    }

    /**
     * @param $subject
     * @return Thread
     */
    public function setSubject($subject)
    {
        return $this->fill([
            'subject_id' => $subject->getKey(),
            'subject_type' => $subject->getMorphClass(),
        ]);
    }
}
