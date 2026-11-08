<?php

namespace Tests\Unit;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    public function test_user_with_an_allowed_role_can_continue(): void
    {
        Auth::shouldReceive('user')
            ->once()
            ->andReturn((object) ['role' => 'principal']);

        $response = (new RoleMiddleware())->handle(
            Request::create('/school/greenfield/staff'),
            fn () => response('allowed'),
            'principal',
            'school_admin'
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_user_with_an_unallowed_role_receives_forbidden_response(): void
    {
        $this->expectException(HttpException::class);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn((object) ['role' => 'student']);

        (new RoleMiddleware())->handle(
            Request::create('/school/greenfield/staff'),
            fn () => response('should not be reached'),
            'principal',
            'school_admin'
        );
    }

    public function test_missing_user_receives_forbidden_response(): void
    {
        $this->expectException(HttpException::class);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn(null);

        (new RoleMiddleware())->handle(
            Request::create('/school/greenfield/staff'),
            fn () => response('should not be reached'),
            'principal'
        );
    }
}
