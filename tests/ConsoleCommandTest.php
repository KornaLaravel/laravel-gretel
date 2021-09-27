<?php

namespace Glhd\Gretel\Tests;

use Glhd\Gretel\Registry;
use Glhd\Gretel\Support\Cache;
use Glhd\Gretel\Tests\Models\Note;
use Glhd\Gretel\Tests\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

class ConsoleCommandTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		
		Route::middleware(SubstituteBindings::class)->group(function() {
			$cb = fn() => 'OK';
			
			Route::get('/', $cb)->name('home')->breadcrumb('Home');
			Route::get('/users', $cb)->name('users.index')->breadcrumb('Users', 'home');
			Route::get('/users/create', $cb)->name('users.create')->breadcrumb('Create User', '.index');
			Route::get('/users/{user}', fn(User $user) => $cb())->name('users.show')->breadcrumb(fn(User $user) => $user->name, '.index');
			Route::get('/users/{user}/notes', fn(User $user) => $cb())->name('notes.index')->breadcrumb('Notes', 'users.show');
			Route::get('/users/{user}/notes/{note}', fn(User $user, Note $note) => $cb())->name('notes.show')->breadcrumb(fn(User $user, Note $note) => $note->note, '.index');
		});
	}
	
	public function test_cache_and_clear_commands(): void
	{
		$cache = $this->app->make(Cache::class);
		
		$this->artisan('breadcrumbs:cache')
			->expectsOutput('Breadcrumbs cached successfully!')
			->assertExitCode(0);
		
		$this->assertFileExists($cache->path());
		
		// Clear existing registry and re-load from cache
		$this->app->forgetInstance(Registry::class);
		$cache->load();
		
		$registry = $this->app->make(Registry::class);
		
		$this->assertEquals(6, $registry->count());
		$this->assertTrue($registry->has('home'));
		$this->assertTrue($registry->has('users.index'));
		$this->assertTrue($registry->has('users.create'));
		$this->assertTrue($registry->has('users.show'));
		$this->assertTrue($registry->has('notes.index'));
		$this->assertTrue($registry->has('notes.show'));
		
		$this->artisan('breadcrumbs:clear');
		$this->assertFileNotExists($cache->path());
	}
	
	public function test_cache_command_triggers_error_if_routes_are_already_cached(): void
	{
		$fs = new Filesystem();
		$cached_routes_path = $this->app->getCachedRoutesPath();
		
		try {
			$fs->put($cached_routes_path, '');
			$this->artisan('breadcrumbs:cache')->assertExitCode(1);
		} finally {
			$fs->delete($cached_routes_path);
			$this->artisan('breadcrumbs:clear');
		}
	}
}
