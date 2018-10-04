<?php

namespace Cmgmyr\Messenger\Test;

date_default_timezone_set('America/New_York');

use AdamWathan\Faktory\Faktory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @var \AdamWathan\Faktory\Faktory
     */
    protected $faktory;

    /**
     * Set up the database, migrations, and initial data.
     */
    public function setUp()
    {
        parent::setUp();

//        $this->configureDatabase();
        $this->migrateTables();
        $this->faktory = new Faktory;
        $load_factories = function ($faktory) {
            require __DIR__ . '/factories.php';
        };
        $load_factories($this->faktory);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('messenger.message_model', 'Cmgmyr\Messenger\Models\Message');
        $app['config']->set('messenger.participant_model', 'Cmgmyr\Messenger\Models\Participant');
        $app['config']->set('messenger.thread_model', 'Cmgmyr\Messenger\Models\Thread');

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app->afterResolving('migrator', function ($migrator) {
            $migrator->path(__DIR__.'/../migrations/');
        });
    }

    /**
     * Run the migrations for the database.
     */
    private function migrateTables()
    {
        $this->createUsersTable();
        $this->seedUsersTable();

        $this->artisan('migrate', ['--database' => 'testbench']);
    }

    /**
     * Create the users table in the database.
     */
    private function createUsersTable()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->enum('notify', ['y', 'n'])->default('y');
            $table->timestamps();
        });
    }

    /**
     * Create some users for the tests to use.
     */
    private function seedUsersTable()
    {
        DB::insert('INSERT INTO ' . DB::getTablePrefix() . 'users (id, name, email, created_at, updated_at) VALUES (?, ?, ?, datetime(), datetime())', [1, 'Chris Gmyr', 'chris@test.com']);
        DB::insert('INSERT INTO ' . DB::getTablePrefix() . 'users (id, name, email, created_at, updated_at) VALUES (?, ?, ?, datetime(), datetime())', [2, 'Adam Wathan', 'adam@test.com']);
        DB::insert('INSERT INTO ' . DB::getTablePrefix() . 'users (id, name, email, created_at, updated_at) VALUES (?, ?, ?, datetime(), datetime())', [3, 'Taylor Otwell', 'taylor@test.com']);
    }
}
