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
        Auth::shouldReceive('user')
            ->once()
            ->andReturn((object) ['is_active' => true]);

        $response = (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('allowed')
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_inactive_user_is_logged_out_and_blocked(): void
    {
        $this->expectException(HttpException::class);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn((object) ['is_active' => false]);

        Auth::shouldReceive('logout')
            ->once();

        (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('should not be reached')
        );
    }

    public function test_missing_user_is_blocked(): void
    {
        $this->expectException(HttpException::class);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn(null);

        (new EnsureActiveUser())->handle(
            Request::create('/school/greenfield/dashboard'),
            fn () => response('should not be reached')
        );
    }
}
