<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Never touch the real media bucket from a test. Without this, every
     * seeder photo went to the dev S3 endpoint (unreachable outside Docker,
     * ~50 s of failed requests per seeder test; with Docker up it would have
     * written into the dev bucket).
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    /**
     * Each staff portal authenticates on its own guard (admin, sales, gudang)
     * and the marketplace on "web" (App\Http\Middleware\UsePortalSession). A test that doesn't name a
     * guard is signed in on both, as if it had logged in at whichever door
     * the request goes to; pass a guard to test the separation itself.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($guard === null) {
            // Every door by name: an earlier portal request leaves its guard as the default.
            foreach (['admin', 'sales', 'web'] as $door) {
                $this->app['auth']->guard($door)->setUser($user);
            }
        }

        return parent::actingAs($user, $guard);
    }
}
