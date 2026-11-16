<?php

namespace Tests\Unit;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    /**
     * Route-level role baseline copied from routes/web.php.
     *
     * @return array<string, list<string>>
     */
    private function permissions(): array
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

    public function test_every_role_in_the_permission_baseline_is_allowed_for_its_module(): void
    {
        foreach ($this->permissions() as $module => $allowedRoles) {
            foreach ($allowedRoles as $role) {
                $this->assertRoleDecision($role, $allowedRoles, true, $module);
            }
        }
    }

    public function test_roles_outside_each_module_allowlist_are_forbidden(): void
    {
        $allRoles = [
            'school_admin', 'principal', 'vice_principal', 'dean', 'hod',
            'class_teacher', 'subject_teacher', 'bursar', 'parent', 'librarian',
        ];

        foreach ($this->permissions() as $module => $allowedRoles) {
            foreach (array_diff($allRoles, $allowedRoles) as $role) {
                $this->assertRoleDecision($role, $allowedRoles, false, $module);
            }
        }
    }

    private function assertRoleDecision(string $role, array $allowedRoles, bool $allowed, string $module): void
    {
        Auth::clearResolvedInstances();

        $guard = \Mockery::mock();
        $guard->shouldReceive('user')->once()->andReturn((object) ['role' => $role]);
        Auth::shouldReceive('guard')->once()->with('school')->andReturn($guard);

        $nextCalled = false;

        try {
            (new RoleMiddleware())->handle(
                Request::create('/school/example/' . $module),
                function () use (&$nextCalled) {
                    $nextCalled = true;
                    return response('allowed');
                },
                ...$allowedRoles
            );

            $this->assertTrue($allowed, sprintf('Role %s unexpectedly accessed %s.', $role, $module));
            $this->assertTrue($nextCalled);
        } catch (HttpException $exception) {
            $this->assertFalse($allowed, sprintf('Role %s was unexpectedly denied %s.', $role, $module));
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertFalse($nextCalled);
        }
    }
}
