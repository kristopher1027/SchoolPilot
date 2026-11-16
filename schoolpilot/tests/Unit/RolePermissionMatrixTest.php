<?php

namespace Tests\Unit;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    /**
     * Route-level role baseline copied from routes/web.php.
     *
     * @return array<string, list<string>>
     */
    private static function permissions(): array
    {
        return [
            'dashboard' => ['school_admin', 'principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar'],
            'teacher-dashboard' => ['class_teacher', 'subject_teacher'],
            'settings' => ['school_admin'],
            'students' => ['school_admin', 'principal', 'dean'],
            'staff' => ['school_admin', 'principal'],
            'fees' => ['school_admin', 'principal', 'bursar'],
            'payroll' => ['school_admin', 'principal', 'bursar'],
            'library' => ['school_admin', 'principal', 'bursar', 'librarian'],
            'inventory' => ['school_admin', 'principal', 'bursar'],
            'transport' => ['school_admin', 'principal', 'bursar'],
            'parent-portal' => ['parent'],
            'attendance' => ['school_admin', 'principal', 'dean', 'hod', 'class_teacher'],
            'scores' => ['school_admin', 'principal', 'dean', 'hod', 'class_teacher', 'subject_teacher'],
            'results' => ['school_admin', 'principal', 'dean', 'hod', 'class_teacher'],
        ];
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function allowedRoleCases(): array
    {
        $cases = [];
        foreach (self::permissions() as $module => $roles) {
            foreach ($roles as $role) {
                $cases[$module . '__' . $role] = [$module, $role, $roles];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function forbiddenRoleCases(): array
    {
        $allRoles = [
            'school_admin', 'principal', 'vice_principal', 'dean', 'hod',
            'class_teacher', 'subject_teacher', 'bursar', 'parent', 'librarian',
        ];
        $cases = [];

        foreach (self::permissions() as $module => $allowedRoles) {
            foreach (array_diff($allRoles, $allowedRoles) as $role) {
                $cases[$module . '__' . $role] = [$module, $role, $allowedRoles];
            }
        }

        return $cases;
    }

    #[DataProvider('allowedRoleCases')]
    public function test_allowed_role_can_access_its_module(string $module, string $role, array $allowedRoles): void
    {
        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn((object) ['role' => $role]);
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        $response = (new RoleMiddleware())->handle(
            Request::create('/school/example/' . $module),
            fn () => response('allowed'),
            ...$allowedRoles
        );

        $this->assertSame('allowed', $response->getContent());
    }

    #[DataProvider('forbiddenRoleCases')]
    public function test_role_outside_module_allowlist_is_forbidden(string $module, string $role, array $allowedRoles): void
    {
        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn((object) ['role' => $role]);
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        (new RoleMiddleware())->handle(
            Request::create('/school/example/' . $module),
            fn () => response('must not be reached'),
            ...$allowedRoles
        );
    }
}
