<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherAssignmentTimetableIntegrityTest extends TestCase
{
    private string $dir;
    private string $db;
    private string $oldCentral;
    private string $oldTenant;
    private string $oldDefault;
    private string $oldTenantDir;
    private bool $createdDir = false;
    private int $classId;
    private int $sectionId;
    private int $subjectId;
    private int $teacherId;
    private int $sessionId;
    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();
        $token = bin2hex(random_bytes(6));
        $this->dir = database_path('tenants');
        if (! is_dir($this->dir)) { mkdir($this->dir, 0700, true); $this->createdDir = true; }
        $this->db = 'schedule-' . $token . '.sqlite';
        $this->oldCentral = (string) config('database.connections.central_sqlite.database');
        $this->oldTenant = (string) config('database.connections.tenant_sqlite.database');
        $this->oldDefault = DB::getDefaultConnection();
        $this->oldTenantDir = (string) config('tenancy.database.sqlite.path');
        Config::set('tenancy.database.sqlite.path', $this->dir);
        Config::set('session.driver', 'array');
        Config::set('database.connections.central_sqlite.database', ':memory:');
        DB::purge('central_sqlite');
        Schema::connection('central_sqlite')->create('tenants', function (Blueprint $t) { $t->string('id')->primary(); $t->json('data')->nullable(); $t->timestamps(); });
        DB::connection('central_sqlite')->table('tenants')->insert(['id'=>'schedule-test','data'=>json_encode(['slug'=>'schedule-test','status'=>'active','db_name'=>$this->db]),'created_at'=>now(),'updated_at'=>now()]);
        $path=$this->dir.'/'.$this->db; touch($path);
        Config::set('database.connections.tenant_sqlite.database',$path); DB::purge('tenant_sqlite');
        $s=Schema::connection('tenant_sqlite');
        $s->create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('role');$t->boolean('is_active')->default(true);$t->rememberToken();$t->timestamps();});
        $s->create('sections',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->timestamps();});
        $s->create('classes',function(Blueprint $t){$t->id();$t->unsignedBigInteger('section_id');$t->string('name');$t->integer('level');$t->timestamps();});
        $s->create('subjects',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->nullable();$t->unsignedBigInteger('section_id')->nullable();$t->unsignedBigInteger('department_id')->nullable();$t->timestamps();});
        $s->create('staff',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('staff_no')->unique();$t->string('designation')->nullable();$t->timestamps();});
        $s->create('academic_sessions',function(Blueprint $t){$t->id();$t->string('name');$t->date('start_date');$t->date('end_date');$t->boolean('is_current')->default(false);$t->timestamps();});
        $s->create('terms',function(Blueprint $t){$t->id();$t->unsignedBigInteger('academic_session_id');$t->string('name');$t->date('start_date');$t->date('end_date');$t->boolean('is_current')->default(false);$t->timestamps();});
        $s->create('teacher_subjects',function(Blueprint $t){$t->id();$t->unsignedBigInteger('staff_id');$t->unsignedBigInteger('class_id');$t->unsignedBigInteger('subject_id');$t->unsignedBigInteger('academic_session_id');$t->unsignedBigInteger('term_id');$t->timestamps();});
        $s->create('timetable_entries',function(Blueprint $t){$t->id();$t->unsignedBigInteger('class_id');$t->unsignedBigInteger('subject_id');$t->unsignedBigInteger('staff_id');$t->unsignedBigInteger('academic_session_id');$t->unsignedBigInteger('term_id');$t->string('day_of_week');$t->time('start_time');$t->time('end_time');$t->string('room')->nullable();$t->timestamps();});
        Config::set('database.default','tenant_sqlite'); DB::setDefaultConnection('tenant_sqlite');
        $admin=DB::table('users')->insertGetId(['name'=>'Admin','email'=>'admin@schedule.test','password'=>Hash::make('Password-2026'),'role'=>'school_admin','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->sectionId=DB::table('sections')->insertGetId(['name'=>'Primary','code'=>'PRI','created_at'=>now(),'updated_at'=>now()]);
        $this->classId=DB::table('classes')->insertGetId(['section_id'=>$this->sectionId,'name'=>'Primary 1','level'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->subjectId=DB::table('subjects')->insertGetId(['name'=>'Maths','section_id'=>$this->sectionId,'created_at'=>now(),'updated_at'=>now()]);
        $this->sessionId=DB::table('academic_sessions')->insertGetId(['name'=>'2026/27','start_date'=>'2026-09-01','end_date'=>'2027-07-31','is_current'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->termId=DB::table('terms')->insertGetId(['academic_session_id'=>$this->sessionId,'name'=>'First Term','start_date'=>'2026-09-01','end_date'=>'2026-12-10','is_current'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $teacherUser=DB::table('users')->insertGetId(['name'=>'Teacher','email'=>'teacher@schedule.test','password'=>Hash::make('Password-2026'),'role'=>'subject_teacher','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->teacherId=DB::table('staff')->insertGetId(['user_id'=>$teacherUser,'staff_no'=>'T-01','designation'=>'Teacher','created_at'=>now(),'updated_at'=>now()]);
        DB::table('teacher_subjects')->insert(['staff_id'=>$this->teacherId,'class_id'=>$this->classId,'subject_id'=>$this->subjectId,'academic_session_id'=>$this->sessionId,'term_id'=>$this->termId,'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs(User::findOrFail($admin),'school')->withSession(['_school_authenticated_slug'=>'schedule-test']);
    }

    protected function tearDown(): void
    {
        Auth::forgetGuards(); DB::purge('tenant_sqlite'); DB::purge('central_sqlite');
        Config::set('database.connections.central_sqlite.database',$this->oldCentral);
        Config::set('database.connections.tenant_sqlite.database',$this->oldTenant);
        Config::set('tenancy.database.sqlite.path',$this->oldTenantDir);
        DB::setDefaultConnection($this->oldDefault);
        @unlink($this->dir.'/'.$this->db); if($this->createdDir){@rmdir($this->dir);}
        parent::tearDown();
    }

    public function test_teacher_assignment_rejects_cross_section_subject_and_wrong_session_term(): void
    {
        $otherSection=DB::table('sections')->insertGetId(['name'=>'Secondary','code'=>'SEC','created_at'=>now(),'updated_at'=>now()]);
        $otherSubject=DB::table('subjects')->insertGetId(['name'=>'Physics','section_id'=>$otherSection,'created_at'=>now(),'updated_at'=>now()]);
        $otherSession=DB::table('academic_sessions')->insertGetId(['name'=>'2027/28','start_date'=>'2027-09-01','end_date'=>'2028-07-31','is_current'=>false,'created_at'=>now(),'updated_at'=>now()]);
        $otherTerm=DB::table('terms')->insertGetId(['academic_session_id'=>$otherSession,'name'=>'First Term','start_date'=>'2027-09-01','end_date'=>'2027-12-10','is_current'=>false,'created_at'=>now(),'updated_at'=>now()]);
        $url='/school/schedule-test/assignments';
        $this->from($url)->post($url,['staff_id'=>$this->teacherId,'class_id'=>$this->classId,'subject_id'=>$otherSubject,'session_id'=>$this->sessionId,'term_id'=>$this->termId])->assertRedirect($url)->assertSessionHasErrors(['subject_id']);
        $this->post($url,['staff_id'=>$this->teacherId,'class_id'=>$this->classId,'subject_id'=>$this->subjectId,'session_id'=>$this->sessionId,'term_id'=>$otherTerm])->assertRedirect($url)->assertSessionHasErrors(['term_id']);
        $this->assertSame(1,DB::table('teacher_subjects')->count());
    }

    public function test_timetable_requires_assignment_and_rejects_duplicate_lesson(): void
    {
        $url='/school/schedule-test/timetable';
        $payload=['class_id'=>$this->classId,'subject_id'=>$this->subjectId,'staff_id'=>$this->teacherId,'academic_session_id'=>$this->sessionId,'term_id'=>$this->termId,'day_of_week'=>'Monday','start_time'=>'09:00','end_time'=>'10:00','room'=>'Room 1'];
        DB::table('teacher_subjects')->delete();
        $this->from($url)->post($url,$payload)->assertRedirect($url)->assertSessionHasErrors(['staff_id']);
        DB::table('teacher_subjects')->insert(['staff_id'=>$this->teacherId,'class_id'=>$this->classId,'subject_id'=>$this->subjectId,'academic_session_id'=>$this->sessionId,'term_id'=>$this->termId,'created_at'=>now(),'updated_at'=>now()]);
        $this->post($url,$payload)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->from($url)->post($url,$payload)->assertRedirect($url)->assertSessionHasErrors(['start_time']);
        $this->assertSame(1,DB::table('timetable_entries')->count());
    }
}
