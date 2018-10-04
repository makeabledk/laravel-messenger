<?php

namespace Cmgmyr\Messenger\Test;

use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use Cmgmyr\Messenger\Models\Thread;
use Cmgmyr\Messenger\Test\Stubs\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use ReflectionClass;

class EloquentThreadTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        Eloquent::unguard();
    }

    /**
     * Activate private/protected methods for testing.
     *
     * @param $name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name)
    {
        $class = new ReflectionClass(Thread::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /** @test */
    public function search_specific_thread_by_name()
    {
        $this->faktory->create('thread', ['id' => 1, 'name' => 'first name']);
        $this->faktory->create('thread', ['id' => 2, 'name' => 'second name']);

        $threads = Thread::getByName('first name');

        $this->assertEquals(1, $threads->count());
        $this->assertEquals(1, $threads->first()->id);
        $this->assertEquals('first name', $threads->first()->name);
    }

    /** @test */
    public function search_threads_by_name()
    {
        $this->faktory->create('thread', ['id' => 1, 'name' => 'first name']);
        $this->faktory->create('thread', ['id' => 2, 'name' => 'second name']);

        $threads = Thread::getByName('%name');

        $this->assertEquals(2, $threads->count());

        $this->assertEquals(1, $threads->first()->id);
        $this->assertEquals('first name', $threads->first()->name);

        $this->assertEquals(2, $threads->last()->id);
        $this->assertEquals('second name', $threads->last()->name);
    }

    /** @test */
    public function it_should_create_a_new_thread()
    {
        $thread = $this->faktory->build('thread');
        $this->assertEquals('Sample thread', $thread->name);

        $thread = $this->faktory->build('thread', ['name' => 'Second sample thread']);
        $this->assertEquals('Second sample thread', $thread->name);
    }

    /** @test */
    public function it_should_return_the_latest_message()
    {
        $oldMessage = $this->faktory->build('message', [
            'created_at' => Carbon::yesterday(),
        ]);

        $newMessage = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'This is the most recent message',
        ]);

        $thread = $this->faktory->create('thread');
        $thread->messages()->saveMany([$oldMessage, $newMessage]);
        $this->assertEquals($newMessage->body, $thread->latestMessage->body);
    }

    /** @test */
    public function it_should_return_all_threads()
    {
        $threadCount = rand(5, 20);

        foreach (range(1, $threadCount) as $index) {
            $this->faktory->create('thread', ['id' => ($index + 1)]);
        }

        $threads = Thread::getAllLatest()->get();

        $this->assertCount($threadCount, $threads);
    }

    /** @test */
    public function it_should_get_all_threads_for_a_user()
    {
        $user = User::firstOrFail();

        $participant_1 = $this->faktory->create('participant', ['user_id' => $user->id]);
        $thread = $this->faktory->create('thread');
        $thread->participants()->saveMany([$participant_1]);

        $thread2 = $this->faktory->create('thread', ['name' => 'Second Thread']);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $user->id, 'thread_id' => $thread2->id]);
        $thread2->participants()->saveMany([$participant_2]);

        $threads = Thread::forUser($user)->get();
        $this->assertCount(2, $threads);
    }

    /** @test */
    public function it_should_get_all_user_entities_for_a_thread()
    {
        $thread = $this->faktory->create('thread');
        $user_1 = $this->faktory->build('participant');
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $thread->participants()->saveMany([$user_1, $user_2]);

        $threadUserIds = $thread->users(User::class)->get()->pluck('id')->toArray();
        $this->assertArraySubset([1, 2], $threadUserIds);
    }

    /** @test */
    public function it_should_get_all_threads_for_a_user_with_new_messages()
    {
        $user = User::findOrFail(1);

        $participant_1 = $this->faktory->create('participant', ['user_id' => $user->id, 'last_read' => Carbon::now()]);
        $thread = $this->faktory->create('thread', ['updated_at' => Carbon::yesterday()]);
        $thread->participants()->saveMany([$participant_1]);

        $thread2 = $this->faktory->create('thread', ['name' => 'Second Thread', 'updated_at' => Carbon::now()]);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $user->id, 'thread_id' => $thread2->id, 'last_read' => Carbon::yesterday()]);
        $thread2->participants()->saveMany([$participant_2]);

        $this->assertEquals(1, Thread::forUserWithNewMessages($user)->count());
        $this->assertEquals(1, Thread::forUserWithNewMessages($user->id, $user->getMorphClass())->count());
    }

    /** @test */
    public function it_should_get_all_threads_shared_by_specified_users()
    {
        list($user1, $user2) = [
            User::findOrFail(1),
            User::findOrFail(2),
        ];

        list($thread1, $thread2) = [
            $this->faktory->create('thread'),
            $this->faktory->create('thread'),
        ];

        $this->faktory->create('participant', ['user_id' => $user1->id, 'thread_id' => $thread1->id]);
        $this->faktory->create('participant', ['user_id' => $user1->id, 'thread_id' => $thread2->id]);
        $this->faktory->create('participant', ['user_id' => $user2->id, 'thread_id' => $thread2->id]);

        $this->assertCount(1, Thread::between([$user1, $user2])->get());
    }

    /** @test */
    public function it_should_add_a_participant_to_a_thread()
    {
        $thread = $this->faktory->create('thread');

        $thread->addParticipant(User::first());

        $this->assertEquals(1, $thread->participants()->count());
    }

    /** @test */
    public function it_should_add_participants_to_a_thread_with_array()
    {
        $thread = $this->faktory->create('thread');

        $thread->addParticipant(User::take(3)->get());

        $this->assertEquals(3, $thread->participants()->count());
    }

    /** @test */
    public function it_should_add_participants_to_a_thread_with_arguments()
    {
        $thread = $this->faktory->create('thread');

        $thread->addParticipant(User::findOrFail(1), User::findOrFail(2));

        $this->assertEquals(2, $thread->participants()->count());
    }

    /** @test */
    public function it_should_mark_the_participant_as_read()
    {
        $user = User::firstOrFail();
        $last_read = Carbon::yesterday();

        $participant = $this->faktory->create('participant', ['user_id' => $user->id, 'last_read' => $last_read]);
        $thread = $this->faktory->create('thread');
        $thread->participants()->saveMany([$participant]);

        $thread->markAsRead($user);

        $this->assertNotEquals($thread->getParticipantFromUser($user)->last_read, $last_read);
    }

    /** @test */
    public function it_should_see_if_thread_is_unread_by_user()
    {
        $user = User::firstOrFail();

        $participant_1 = $this->faktory->create('participant', ['user_id' => $user->id, 'last_read' => Carbon::now()]);
        $thread = $this->faktory->create('thread', ['updated_at' => Carbon::yesterday()]);
        $thread->participants()->saveMany([$participant_1]);

        $this->assertFalse($thread->isUnread($user));

        $thread2 = $this->faktory->create('thread', ['name' => 'Second Thread', 'updated_at' => Carbon::now()]);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $user->id, 'thread_id' => $thread2->id, 'last_read' => Carbon::yesterday()]);
        $thread2->participants()->saveMany([$participant_2]);

        $this->assertTrue($thread2->isUnread($user));
    }

    /** @test */
    public function it_should_get_a_participant_from_a_user()
    {
        $user = User::firstOrFail();

        $participant = $this->faktory->create('participant', ['user_id' => $user->id]);
        $thread = $this->faktory->create('thread');
        $thread->participants()->saveMany([$participant]);

        $this->assertInstanceOf(Participant::class, $thread->getParticipantFromUser($user));
        $this->assertInstanceOf(Participant::class, $thread->getParticipantFromUser($user->id, $user->getMorphClass()));
    }

    /**
     * @test
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function it_should_throw_an_exception_when_participant_is_not_found()
    {
        $thread = $this->faktory->create('thread');

        $thread->getParticipantFromUser(99, User::class);
    }

    /** @test */
    public function it_should_activate_all_deleted_participants()
    {
        $deleted_at = Carbon::yesterday();
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant', ['deleted_at' => $deleted_at]);
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2, 'deleted_at' => $deleted_at]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3, 'deleted_at' => $deleted_at]);

        $thread->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $participants = $thread->participants();
        $this->assertEquals(0, $participants->count());

        $thread->activateAllParticipants();

        $participants = $thread->participants();
        $this->assertEquals(3, $participants->count());
    }

    /** @test */
    public function it_should_get_participants_string()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $thread->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $string = $thread->participantsString();
        $this->assertEquals('Chris Gmyr, Adam Wathan, Taylor Otwell', $string);

        $string = $thread->participantsString(User::findOrFail(1));
        $this->assertEquals('Adam Wathan, Taylor Otwell', $string);

        $string = $thread->participantsString(User::findOrFail(1), ['email']);
        $this->assertEquals('adam@test.com, taylor@test.com', $string);
    }

    /** @test */
    public function it_can_check_if_user_is_a_participant()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant', ['user_id' => 1]);
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);

        $this->assertTrue($thread->hasParticipant(User::findOrFail(1)));
        $this->assertTrue($thread->hasParticipant(User::findOrFail(2)));
        $this->assertFalse($thread->hasParticipant(User::findOrFail(3)));
    }

    /** @test */
    public function it_should_remove_a_single_participant()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);

        $thread->removeParticipant($participant_2->user);

        $this->assertEquals(1, $thread->participants()->count());
    }

    /** @test */
    public function it_should_remove_a_group_of_participants_with_array()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant', ['user_id' => 1]);
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);

        $thread->removeParticipant([User::findOrFail(1), User::findOrFail(2)]);

        $this->assertEquals(0, $thread->participants()->count());
    }

    /** @test */
    public function it_should_remove_a_group_of_participants_with_arguments()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant', ['user_id' => 1]);
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);

        $thread->removeParticipant(User::findOrFail(1), User::findOrFail(2));

        $this->assertEquals(0, $thread->participants()->count());
    }

    /** @test */
    public function it_should_get_all_unread_messages_for_user()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $message_1 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 1',
        ]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);
        $thread->messages()->saveMany([$message_1]);

        $thread->markAsRead($participant_2->user);

        // Simulate delay after last read
        sleep(1);

        $message_2 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 2',
        ]);

        $thread->messages()->saveMany([$message_2]);

//        dd($thread->userUnreadMessages($participant_1->user));

        $this->assertEquals('Message 1', $thread->userUnreadMessages($participant_1->user)->first()->body);
        $this->assertCount(2, $thread->userUnreadMessages($participant_1->user));

        $this->assertEquals('Message 2', $thread->userUnreadMessages($participant_2->user)->first()->body);
        $this->assertCount(1, $thread->userUnreadMessages($participant_2->user));
    }

    /** @test */
    public function it_should_get_count_of_all_unread_messages_for_user()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $message_1 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 1',
        ]);

        $thread->participants()->saveMany([$participant_1, $participant_2]);
        $thread->messages()->saveMany([$message_1]);

        $thread->markAsRead($participant_2->user);

        // Simulate delay after last read
        sleep(1);

        $message_2 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 2',
        ]);

        $thread->messages()->saveMany([$message_2]);

        $this->assertEquals(2, $thread->userUnreadMessagesCount($participant_1->user));

        $this->assertEquals(1, $thread->userUnreadMessagesCount($participant_2->user));
    }

    /** @test */
    public function it_should_return_zero_unread_messages_when_user_not_participant()
    {
        $thread = $this->faktory->create('thread');

        $this->assertEquals(0, $thread->userUnreadMessagesCount(User::first()));
    }

    /** @test */
    public function it_should_get_the_creator_of_a_thread()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $thread->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $message_1 = $this->faktory->build('message', ['created_at' => Carbon::yesterday()]);
        $message_2 = $this->faktory->build('message', ['user_id' => 2]);
        $message_3 = $this->faktory->build('message', ['user_id' => 3]);

        $thread->messages()->saveMany([$message_1, $message_2, $message_3]);

        $this->assertEquals('Chris Gmyr', $thread->creator()->name);
    }

    /** @test */
    public function it_returns_null_when_getting_the_creator_of_a_thread_without_messages()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $thread->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $this->assertNull($thread->creator());
    }

    /**
     * TODO currently not supported.
     */
    public function it_should_get_the_creator_of_a_thread_without_messages()
    {
        $thread = $this->faktory->create('thread');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $thread->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $this->assertFalse($thread->creator()->exists);
        $this->assertNull($thread->creator()->name);
    }

    /** @test **/
    public function it_can_attach_to_a_subject()
    {
        $thread = $this->faktory->create('thread');
        $subject = User::first(); // could be any eloquent model

        $thread->setSubject($subject)->save();

        $this->assertTrue($thread->subject->is($subject));
    }

    /** @test **/
    public function it_can_find_a_thread_for_a_given_subject()
    {
        $subject = User::first(); // could be any eloquent model
        $this->faktory->create('thread')->setSubject($subject)->save();

        $this->assertEquals(1, Thread::forSubject($subject)->count());
    }

    /** @test **/
    function it_can_send_message_through_a_thread()
    {
        $thread = $this->faktory->create('thread');

        // As a string
        $message = $thread->send('Hello world', User::firstOrFail());
        $this->assertEquals('Hello world', $message->body);
        $this->assertTrue($message->exists);

        // As an instance
        $message = $thread->send(new Message(['body' => 'Hello world']), User::firstOrFail());
        $this->assertEquals('Hello world', $message->body);
        $this->assertTrue($message->exists);
    }

    /** @test **/
    function it_attaches_a_message_user_to_an_existing_participant()
    {
        $thread = $this->faktory->create('thread');
        $thread->addParticipant($user = User::firstOrFail());

        $participant = $thread->participants()->first();

        $message = $thread->send('Hello world', $user);

        $this->assertEquals($message->participant->id, $participant->id);
        $this->assertEquals($message->participant->user->id, $user->id);
    }

    /** @test **/
    function it_creates_a_new_participant_for_a_message_user()
    {
        $thread = $this->faktory->create('thread');

        $message = $thread->send('Hello world', $user = User::firstOrFail());

        $this->assertEquals($message->participant->thread_id, $thread->id);
        $this->assertEquals($message->participant->user->id, $user->id);
    }
}
