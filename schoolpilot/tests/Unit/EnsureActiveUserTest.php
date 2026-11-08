<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureActiveUserTest extends TestCase
{
    public function test_active_user_can_continue(): void
    {
        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn((object) ['is_active' => true]);
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        $response = (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('allowed')
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_inactive_user_is_logged_out_and_blocked(): void
    {
        $this->expectException(HttpException::class);

        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn((object) ['is_active' => false]);
        $guard->shouldReceive('logout')->once();
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('should not be reached')
        );
    }

    public function test_missing_user_is_blocked(): void
    {
        $this->expectException(HttpException::class);

        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn(null);
        $guard->shouldReceive('logout')->once();
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('should not be reached')
        );
    }
}
