<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// الصفحة الرئيسية: كل مستخدم إلى بوابته
Route::get('/', function () {
    if (Auth::check()) {
        return redirect(Auth::user()->homeUrl());
    }

    if (Auth::guard('student')->check()) {
        return redirect()->route('student.home');
    }

    // التطبيق المثبّت يفتح على آخر بوابة استُخدمت على هذا الجهاز
    return redirect()->route(request()->cookie('portal') === 'student' ? 'student.login' : 'login');
})->name('home');

// بوابة الطلاب
Route::prefix('student')->name('student.')->group(function () {
    Route::middleware('guest:student')->group(function () {
        Route::get('/login', [Student\AuthController::class, 'show'])->name('login');
        Route::post('/login', [Student\AuthController::class, 'login'])->name('login.attempt');
    });
    Route::middleware('auth:student')->group(function () {
        Route::post('/logout', [Student\AuthController::class, 'logout'])->name('logout');
        Route::get('/password', [Student\AuthController::class, 'editPassword'])->name('password');
        Route::put('/password', [Student\AuthController::class, 'updatePassword'])->name('password.update');
        Route::middleware('student.ready')->group(function () {
            Route::get('/', [Student\HomeController::class, 'index'])->name('home');
            Route::get('/quiz/{quiz}', [Student\QuizController::class, 'show'])->name('quiz');
            Route::post('/quiz/{quiz}/start', [Student\QuizController::class, 'start'])->name('quiz.start');
            Route::post('/quiz/{quiz}/submit', [Student\QuizController::class, 'submit'])->name('quiz.submit');
        });
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::view('/no-access', 'auth.no-access')->name('no-access');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // بوابة المدرس
    Route::middleware('role:teacher')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/', [Teacher\HomeController::class, 'index'])->name('home');
        Route::get('/sheet/{assignment}', [Teacher\SheetController::class, 'show'])->name('sheet');
        Route::post('/sheet/{assignment}/mark', [Teacher\SheetController::class, 'saveMark'])->name('sheet.mark');
        Route::get('/sheet/{assignment}/export', [Teacher\SheetController::class, 'export'])->name('sheet.export');
        Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'teacher'])->name('analytics');
        // ملاحظات الطلاب
        Route::get('/sheet/{assignment}/notes', [Teacher\NoteController::class, 'index'])->name('notes');
        Route::post('/sheet/{assignment}/notes', [Teacher\NoteController::class, 'store'])->name('notes.store');
        Route::delete('/notes/{note}', [Teacher\NoteController::class, 'destroy'])->name('notes.destroy');
        // الاختبارات الإلكترونية
        Route::get('/quizzes', [Teacher\QuizController::class, 'index'])->name('quizzes.index');
        Route::get('/quizzes/create', [Teacher\QuizController::class, 'create'])->name('quizzes.create');
        Route::post('/quizzes', [Teacher\QuizController::class, 'store'])->name('quizzes.store');
        Route::get('/quizzes/{quiz}/edit', [Teacher\QuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('/quizzes/{quiz}', [Teacher\QuizController::class, 'update'])->name('quizzes.update');
        Route::delete('/quizzes/{quiz}', [Teacher\QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::get('/quizzes/{quiz}/results', [Teacher\QuizController::class, 'results'])->name('quizzes.results');
        Route::post('/quizzes/{quiz}/publish', [Teacher\QuizController::class, 'publishResults'])->name('quizzes.publish');
        Route::get('/quizzes/{quiz}/review/{attempt}', [Teacher\QuizController::class, 'review'])->name('quizzes.review');
        Route::post('/quizzes/{quiz}/review/{attempt}', [Teacher\QuizController::class, 'saveReview'])->name('quizzes.review.save');
        Route::post('/quizzes/{quiz}/transfer', [Teacher\QuizController::class, 'transfer'])->name('quizzes.transfer');
    });

    // لوحة الإدارة: المدير العام وكل موظف حسب صلاحياته
    Route::middleware('role:admin,staff')->prefix('admin')->name('admin.')->group(function () {

        // متابعة الرصد والدرجات
        Route::middleware('perm:marks,view')->group(function () {
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
            Route::get('marks/{assignment}', [Admin\MarkController::class, 'show'])->name('marks.show');
            Route::get('marks/{assignment}/export', [Admin\MarkController::class, 'export'])->name('marks.export');
            Route::get('export-all', [Admin\MarkController::class, 'exportAll'])->name('marks.export-all');
            Route::get('analytics', [\App\Http\Controllers\AnalyticsController::class, 'admin'])->name('analytics');
        });
        Route::middleware('perm:marks,edit')->group(function () {
            Route::post('marks/{assignment}/mark', [Admin\MarkController::class, 'saveMark'])->name('marks.save');
            // رصد الدرجات نيابة عن المدرس
            Route::get('entry', [Admin\MarkEntryController::class, 'index'])->name('entry.index');
            Route::get('entry/{teacher}', [Admin\MarkEntryController::class, 'teacher'])->name('entry.teacher');
        });

        // اعتماد الدرجات
        Route::get('approvals', [Admin\ApprovalController::class, 'index'])->middleware('perm:approvals,view')->name('approvals.index');
        Route::post('approvals', [Admin\ApprovalController::class, 'apply'])->middleware('perm:approvals,edit')->name('approvals.apply');

        // المدرسون وتكليفاتهم
        Route::middleware('perm:teachers,edit')->group(function () {
            Route::get('teachers/create', [Admin\TeacherController::class, 'create'])->name('teachers.create');
            Route::post('teachers', [Admin\TeacherController::class, 'store'])->name('teachers.store');
            Route::get('teachers/{teacher}/edit', [Admin\TeacherController::class, 'edit'])->name('teachers.edit');
            Route::put('teachers/{teacher}', [Admin\TeacherController::class, 'update'])->name('teachers.update');
            Route::delete('teachers/{teacher}', [Admin\TeacherController::class, 'destroy'])->name('teachers.destroy');
            Route::post('teachers/{teacher}/assignments', [Admin\AssignmentController::class, 'store'])->name('teachers.assignments.store');
            Route::delete('assignments/{assignment}', [Admin\AssignmentController::class, 'destroy'])->name('assignments.destroy');
        });
        Route::middleware('perm:teachers,view')->group(function () {
            Route::get('teachers', [Admin\TeacherController::class, 'index'])->name('teachers.index');
            Route::get('teachers/{teacher}/assignments', [Admin\AssignmentController::class, 'index'])->name('teachers.assignments');
        });

        // الصفوف والشعب
        Route::get('sections', [Admin\SectionController::class, 'index'])->middleware('perm:sections,view')->name('sections.index');
        Route::middleware('perm:sections,edit')->group(function () {
            Route::post('sections', [Admin\SectionController::class, 'store'])->name('sections.store');
            Route::put('sections/{section}', [Admin\SectionController::class, 'update'])->name('sections.update');
            Route::delete('sections/{section}', [Admin\SectionController::class, 'destroy'])->name('sections.destroy');
            Route::post('grade-levels', [Admin\SectionController::class, 'storeGrade'])->name('grades.store');
        });

        // المواد
        Route::get('subjects', [Admin\SubjectController::class, 'index'])->middleware('perm:subjects,view')->name('subjects.index');
        Route::middleware('perm:subjects,edit')->group(function () {
            Route::post('subjects', [Admin\SubjectController::class, 'store'])->name('subjects.store');
            Route::put('subjects/{subject}', [Admin\SubjectController::class, 'update'])->name('subjects.update');
            Route::delete('subjects/{subject}', [Admin\SubjectController::class, 'destroy'])->name('subjects.destroy');
        });

        // عناصر الدرجة
        Route::get('components', [Admin\ComponentController::class, 'index'])->middleware('perm:components,view')->name('components.index');
        Route::middleware('perm:components,edit')->group(function () {
            Route::post('components', [Admin\ComponentController::class, 'store'])->name('components.store');
            Route::put('components/{component}', [Admin\ComponentController::class, 'update'])->name('components.update');
            Route::delete('components/{component}', [Admin\ComponentController::class, 'destroy'])->name('components.destroy');
        });

        // الطلاب
        Route::get('students', [Admin\StudentController::class, 'index'])->middleware('perm:students,view')->name('students.index');
        Route::middleware('perm:students,edit')->group(function () {
            Route::post('students', [Admin\StudentController::class, 'store'])->name('students.store');
            Route::post('students/move', [Admin\StudentController::class, 'move'])->name('students.move');
            Route::get('students/import', [Admin\StudentController::class, 'importForm'])->name('students.import');
            Route::post('students/import', [Admin\StudentController::class, 'import'])->name('students.import.run');
            Route::post('students/import/confirm', [Admin\StudentController::class, 'importConfirm'])->name('students.import.confirm');
            Route::put('students/{student}', [Admin\StudentController::class, 'update'])->name('students.update');
            Route::delete('students/{student}', [Admin\StudentController::class, 'destroy'])->name('students.destroy');
        });

        // بوابة الطلاب: نشر الدرجات وكلمات المرور
        Route::get('portal', [Admin\PortalController::class, 'index'])->middleware('perm:portal,view')->name('portal.index');
        Route::get('portal/credentials', [Admin\PortalController::class, 'credentials'])->middleware('perm:portal,view')->name('portal.credentials');
        Route::get('portal/credentials/export', [Admin\PortalController::class, 'exportCredentials'])->middleware('perm:portal,edit')->name('portal.credentials.export');
        Route::get('portal/credentials/print', [Admin\PortalController::class, 'printCredentials'])->middleware('perm:portal,edit')->name('portal.credentials.print');
        Route::middleware('perm:portal,edit')->group(function () {
            Route::post('portal/publish', [Admin\PortalController::class, 'publish'])->name('portal.publish');
            Route::post('portal/lock', [Admin\PortalController::class, 'lock'])->name('portal.lock');
            Route::post('portal/sections/{section}/issue', [Admin\PortalController::class, 'issueSection'])->name('portal.issue-section');
            Route::post('portal/students/{student}/issue', [Admin\PortalController::class, 'issueStudent'])->name('portal.issue-student');
            Route::put('portal/students/{student}/password', [Admin\PortalController::class, 'setPassword'])->name('portal.set-password');
            Route::put('portal/students/{student}/suspend', [Admin\PortalController::class, 'suspend'])->name('portal.suspend');
            Route::delete('portal/students/{student}/suspend', [Admin\PortalController::class, 'unsuspend'])->name('portal.unsuspend');
        });

        // الاختبارات: الإدارة تتابعها وتنشئها نيابة عن المدرسين
        Route::middleware('perm:quizzes,view')->group(function () {
            Route::get('quizzes', [Teacher\QuizController::class, 'index'])->name('quizzes.index');
            Route::get('quizzes/{quiz}/results', [Teacher\QuizController::class, 'results'])->name('quizzes.results');
        });
        Route::middleware('perm:quizzes,edit')->group(function () {
            Route::get('quizzes/create', [Teacher\QuizController::class, 'create'])->name('quizzes.create');
            Route::post('quizzes', [Teacher\QuizController::class, 'store'])->name('quizzes.store');
            Route::get('quizzes/{quiz}/edit', [Teacher\QuizController::class, 'edit'])->name('quizzes.edit');
            Route::put('quizzes/{quiz}', [Teacher\QuizController::class, 'update'])->name('quizzes.update');
            Route::delete('quizzes/{quiz}', [Teacher\QuizController::class, 'destroy'])->name('quizzes.destroy');
            Route::post('quizzes/{quiz}/publish', [Teacher\QuizController::class, 'publishResults'])->name('quizzes.publish');
            Route::get('quizzes/{quiz}/review/{attempt}', [Teacher\QuizController::class, 'review'])->name('quizzes.review');
            Route::post('quizzes/{quiz}/review/{attempt}', [Teacher\QuizController::class, 'saveReview'])->name('quizzes.review.save');
            Route::post('quizzes/{quiz}/transfer', [Teacher\QuizController::class, 'transfer'])->name('quizzes.transfer');
        });

        // المستخدمون والصلاحيات: للمدير العام فقط
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [Admin\UserController::class, 'create'])->name('users.create');
            Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
