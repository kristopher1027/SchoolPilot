<?php

namespace Tests\\Unit;

use App\\Http\\Controllers\\Tenant\\ScoreController;
use App\\Models\\Tenant\\Staff;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use ReflectionMethod;
use Symfony\\Component\\HttpKernel\\Exception\\HttpException;
use Tests\\TestCase;

class ScoreTeacherAssignmentAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('class_teachers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('academic_session_id');
        });

        Schema::create('teacher_subjects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('term_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('class_teachers');

        parent::tearDown();
    }

    public function test_class_teacher_is_authorized_only_for_assigned_class_and_session(): void
    {
        DB::table('class_teachers')->insert([
            'staff_id' => 7,
            'class_id' => 12,
            'academic_session_id' => 3,
        ]);

        $this->authorizeAs('class_teacher', 7, 12, 55, 3, 8);
        $this->assertForbidden(fn () => $this->authorizeAs('class_teacher', 7, 13, 55, 3, 8));
        $this->assertForbidden(fn () => $this->authorizeAs('class_teacher', 7, 12, 55, 4, 8));
    }

    public function test_subject_teacher_requires_exact_class_subject_session_and_term_assignment(): void
    {
        DB::table('teacher_subjects')->insert([
            'staff_id' => 9,
            'class_id' => 12,
            'subject_id' => 55,
            'academic_session_id' => 3,
            'term_id' => 8,
        ]);

        $this->authorizeAs('subject_teacher', 9, 12, 55, 3, 8);
        $this->assertForbidden(fn () => $this->authorizeAs('subject_teacher', 9, 13, 55, 3, 8));
        $this->assertForbidden(fn () => $this->authorizeAs('subject_teacher', 9, 12, 56, 3, 8));
        $this->assertForbidden(fn () => $this->authorizeAs('subject_teacher', 9, 12, 55, 4, 8));
        $this->assertForbidden(fn () => $this->authorizeAs('subject_teacher', 9, 12, 55, 3, 9));
    }

    public function test_subject_teacher_cannot_use_class_teacher_assignment_to_authorize_class_wide_submission(): void
    {
        DB::table('class_teachers')->insert([
            'staff_id' => 9,
            'class_id' => 12,
            'academic_session_id' => 3,
        ]);

        $this->assertForbidden(fn () => $this->authorizeAs('subject_teacher', 9, 12, null, 3, 8));
    }

    private function authorizeAs(
        string $role,
        int $staffId,
        int $classId,
        ?int $subjectId,
        int $sessionId,
        int $termId
    ): void {
        Auth::shouldReceive('user')->andReturn((object) ['role' => $role]);

        $staff = new Staff();
        $staff->setAttribute('id', $staffId);

        $controller = new ScoreController();
        $method = new ReflectionMethod($controller, 'authorizeTeacherAssignment');
        $method->setAccessible(true);
        $method->invoke($controller, $staff, $classId, $subjectId, $sessionId, $termId);
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected assignment authorization to reject this request.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
