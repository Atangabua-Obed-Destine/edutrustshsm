<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Admin\SequenceController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SubjectEnrollmentController;
use App\Http\Controllers\Admin\SequenceEnrollmentController;
use App\Http\Controllers\Admin\ClassSectionController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\FeeStructureController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\MarksController;
use App\Http\Controllers\Admin\ExamScheduleController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\TimetableController;
use App\Http\Controllers\Admin\ReportCardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ConfigurationHealthController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\FeeCategoryController;
use App\Http\Controllers\Admin\FeeDiscountController;
use App\Http\Controllers\Admin\FeeReportController;
use App\Http\Controllers\Admin\FeeCollectionController;
use App\Http\Controllers\Admin\FeeQuickAssignController;
use App\Http\Controllers\Admin\FeeAssignmentHistoryController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\BulkUploadController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SubjectAddDropController;
use App\Http\Controllers\Admin\ExamPublishingController;
use App\Http\Controllers\Admin\GroupEnrolController;
use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\IdCardController;
use App\Http\Controllers\Admin\PaymentPlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\LevelContextController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\IncomeCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\OutcomeController;
use App\Http\Controllers\Admin\PaymentAccountController;
use App\Http\Controllers\Admin\PaymentAccountTransferController;
use App\Http\Controllers\Admin\PaymentAccountReportController;
use App\Http\Controllers\Admin\BudgetController;
use App\Http\Controllers\Admin\BudgetAllocationController;
use App\Http\Controllers\Admin\BudgetDashboardController;
use App\Http\Controllers\Admin\BudgetReportController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\FiscalYearController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\AccountMappingController;
use App\Http\Controllers\Admin\AccountingReportsController;
use App\Http\Controllers\Admin\FeeFineController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StudentCreditController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\WorkShiftTypeController;
use App\Http\Controllers\Admin\AllowanceTypeController;
use App\Http\Controllers\Admin\DeductionTypeController;
use App\Http\Controllers\Admin\TaxGroupController;
use App\Http\Controllers\Admin\TaxSettingController;
use App\Http\Controllers\Admin\StaffTaxReportController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Apply\ApplicantAuthController;
use App\Http\Controllers\Apply\ApplicationController;
use App\Http\Controllers\ParentPortal\ParentAuthController;
use App\Http\Controllers\ParentPortal\ParentDashboardController;
use App\Http\Controllers\ParentPortal\ParentStudentController;
use App\Http\Controllers\ParentPortal\ParentFeesController;
use App\Http\Controllers\ParentPortal\ParentReportCardController;
use App\Http\Controllers\ParentPortal\ParentSubjectController;
use App\Http\Controllers\ParentPortal\ParentTimetableController;
use App\Http\Controllers\ParentPortal\ParentAttendanceController;
use App\Http\Controllers\ParentPortal\ParentPaymentController;
use App\Http\Controllers\ParentPortal\ParentPtaController;
use App\Http\Controllers\Admin\ParentPortalController;
use App\Http\Controllers\Admin\ParentPaymentVerificationController;
use App\Http\Controllers\Admin\PtaController;
use App\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

// Redirect root to login or dashboard
Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->homeRoute() ?? 'login')
    : redirect()->route('login'));

// Language switcher
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'fr'])) {
        session()->put('locale', $locale);
    }
    return redirect()->back();
})->name('lang.switch');

// ═══════════════════════════════════════════════════════════════
// Applicant Portal (public-facing admission application)
// ═══════════════════════════════════════════════════════════════
Route::prefix('apply')->name('apply.')->group(function () {
    // Auth (guest only)
    Route::middleware('guest:applicant')->group(function () {
        Route::get('/register', [ApplicantAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [ApplicantAuthController::class, 'register'])->middleware('throttle:login')->name('register.submit');
        Route::get('/login', [ApplicantAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [ApplicantAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    });
    Route::post('/logout', [ApplicantAuthController::class, 'logout'])->name('logout');

    // Protected (applicant must be logged in)
    Route::middleware('auth:applicant')->group(function () {
        Route::get('/', [ApplicationController::class, 'dashboard'])->name('dashboard');
        Route::get('/new', [ApplicationController::class, 'create'])->name('create');
        Route::post('/new', [ApplicationController::class, 'store'])->name('store');
        Route::get('/application/{application}', [ApplicationController::class, 'show'])->name('show');
        Route::get('/form-streams/{form}', [ApplicationController::class, 'getFormStreams'])->name('form-streams');
    });
});

// ═══════════════════════════════════════════════════════════════
// Parent / Guardian Portal
// ═══════════════════════════════════════════════════════════════
Route::prefix('parent')->name('parent.')->group(function () {
    // Auth (guest only)
    Route::middleware('guest:guardians')->group(function () {
        Route::get('/login', [ParentAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [ParentAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
        Route::get('/claim', [ParentAuthController::class, 'showClaim'])->name('claim');
        Route::post('/claim', [ParentAuthController::class, 'claim'])->middleware('throttle:login')->name('claim.submit');
    });
    Route::post('/logout', [ParentAuthController::class, 'logout'])->name('logout');

    // Protected (guardian must be logged in)
    Route::middleware('auth:guardians')->group(function () {
        Route::get('/', [ParentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/student/{student}', [ParentStudentController::class, 'show'])->name('student.show');

        // Per-child read-only records
        Route::get('/student/{student}/fees', [ParentFeesController::class, 'index'])->name('fees.index');
        Route::get('/student/{student}/report-cards', [ParentReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('/student/{student}/report-cards/{termResult}', [ParentReportCardController::class, 'show'])->name('report-cards.show');
        Route::get('/student/{student}/subjects', [ParentSubjectController::class, 'index'])->name('subjects.index');
        Route::get('/student/{student}/timetable', [ParentTimetableController::class, 'index'])->name('timetable.index');
        Route::get('/student/{student}/attendance', [ParentAttendanceController::class, 'index'])->name('attendance.index');

        // Payment receipt submission
        Route::get('/payments', [ParentPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/submit', [ParentPaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments/submit', [ParentPaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{submission}', [ParentPaymentController::class, 'show'])->name('payments.show');

        // PTA
        Route::get('/pta', [ParentPtaController::class, 'index'])->name('pta.index');
        Route::get('/pta/announcements', [ParentPtaController::class, 'announcements'])->name('pta.announcements');
        Route::get('/pta/meetings', [ParentPtaController::class, 'meetings'])->name('pta.meetings');
        Route::get('/pta/levies', [ParentPtaController::class, 'levies'])->name('pta.levies');
    });
});

// Auth Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth')->group(function () {

    // Admin routes (super_admin and admin only)
    Route::middleware('role:super_admin,admin')->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // School-level working context switcher (Nursery/Primary vs Secondary)
        Route::post('/level-context', [LevelContextController::class, 'switch'])->name('level-context.switch');

        // Branch (campus) working context switcher
        Route::post('/branch-context', [\App\Http\Controllers\Admin\BranchContextController::class, 'switch'])->name('branch-context.switch');

        // Audit trail (read-only)
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
        Route::get('audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit-log.show');

        // Branch management (owner / super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('branches', [\App\Http\Controllers\Admin\BranchController::class, 'index'])->name('branches.index');
            Route::post('branches', [\App\Http\Controllers\Admin\BranchController::class, 'store'])->name('branches.store');
            Route::put('branches/{branch}', [\App\Http\Controllers\Admin\BranchController::class, 'update'])->name('branches.update');
            Route::post('branches/{branch}/users', [\App\Http\Controllers\Admin\BranchController::class, 'assignUsers'])->name('branches.users');
        });

        // Admissions
        Route::prefix('admissions')->name('admissions.')->group(function () {
            Route::get('applications', [AdmissionController::class, 'index'])->name('applications.index');
            Route::get('applications/{application}', [AdmissionController::class, 'show'])->name('applications.show');
            Route::patch('applications/{application}/status', [AdmissionController::class, 'updateStatus'])->name('applications.update-status');
            Route::post('applications/{application}/accept', [AdmissionController::class, 'accept'])->name('applications.accept');
            Route::post('applications/{application}/reject', [AdmissionController::class, 'reject'])->name('applications.reject');
            Route::post('applications/{application}/enrol', [AdmissionController::class, 'enrol'])->name('applications.enrol');
            Route::get('sections-by-form/{form}', [AdmissionController::class, 'sectionsByForm'])->name('sections-by-form');
            Route::get('streams-by-form/{form}', [AdmissionController::class, 'streamsByForm'])->name('streams-by-form');

            // ID Cards
            Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
            Route::get('id-cards/sections-by-form/{form}', [IdCardController::class, 'sectionsByForm'])->name('id-cards.sections-by-form');
            Route::post('id-cards/print', [IdCardController::class, 'print'])->name('id-cards.print');
            Route::post('id-cards/update-photo/{student}', [IdCardController::class, 'updatePhoto'])->name('id-cards.update-photo');
        });

        // Academic Sessions
        Route::resource('sessions', AcademicSessionController::class)->except(['show']);
        Route::post('sessions/{session}/activate', [AcademicSessionController::class, 'activate'])->name('sessions.activate');

        // Terms
        Route::resource('terms', TermController::class)->except(['show']);
        Route::patch('terms/{term}/set-current', [TermController::class, 'setCurrent'])->name('terms.set-current');

        // Exam Sequences
        Route::resource('sequences', SequenceController::class)->except(['show']);

        // Forms
        Route::resource('forms', FormController::class)->except(['show']);

        // Streams
        Route::resource('streams', StreamController::class)->except(['show']);

        // Subjects
        Route::resource('subjects', SubjectController::class)->except(['show']);

        // Subject Enrollment (assign subjects to forms/streams)
        Route::get('subject-enrollments', [SubjectEnrollmentController::class, 'index'])->name('subject-enrollments.index');
        Route::get('subject-enrollments/{form}', [SubjectEnrollmentController::class, 'configure'])->name('subject-enrollments.configure');
        Route::post('subject-enrollments/{form}', [SubjectEnrollmentController::class, 'save'])->name('subject-enrollments.save');

        // Sequence Enrollment (assign sequences to forms/streams/terms)
        Route::get('sequence-enrollments', [SequenceEnrollmentController::class, 'index'])->name('sequence-enrollments.index');
        Route::get('sequence-enrollments/{form}', [SequenceEnrollmentController::class, 'configure'])->name('sequence-enrollments.configure');
        Route::post('sequence-enrollments/{form}', [SequenceEnrollmentController::class, 'save'])->name('sequence-enrollments.save');

        // Class Sections
        Route::resource('class-sections', ClassSectionController::class)->except(['show']);

        // Batches
        Route::resource('batches', BatchController::class)->except(['show']);

        // Classrooms
        Route::resource('rooms', RoomController::class)->only(['index', 'store', 'update', 'destroy']);

        // Fee Structures
        Route::resource('fee-structures', FeeStructureController::class)->except(['show']);
        Route::get('fee-structures/streams-by-form/{form}', [FeeStructureController::class, 'getStreamsByForm'])->name('fee-structures.streams-by-form');
        Route::get('fee-structures/load-categories', [FeeStructureController::class, 'loadCategories'])->name('fee-structures.load-categories');
        Route::get('fee-structures/load-existing', [FeeStructureController::class, 'loadExisting'])->name('fee-structures.load-existing');

        // Payments
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('students/{student}/fees', [PaymentController::class, 'studentFees'])->name('student-fees');

        // Exam Schedule
        Route::get('exam-schedules', [ExamScheduleController::class, 'index'])->name('exam-schedules.index');
        Route::get('exam-schedules/sections-by-form/{form}', [ExamScheduleController::class, 'sectionsByForm'])->name('exam-schedules.sections-by-form');
        Route::get('exam-schedules/subjects-by-form/{form}', [ExamScheduleController::class, 'subjectsByForm'])->name('exam-schedules.subjects-by-form');
        Route::post('exam-schedules/save', [ExamScheduleController::class, 'save'])->name('exam-schedules.save');
        Route::delete('exam-schedules/{examSchedule}', [ExamScheduleController::class, 'destroy'])->name('exam-schedules.destroy');

        // Marks / Exam Management
        Route::get('marks', [MarksController::class, 'index'])->name('marks.index');
        Route::get('marks/sections-by-form/{form}', [MarksController::class, 'sectionsByForm'])->name('marks.sectionsByForm');
        Route::get('marks/subjects-by-form/{form}', [MarksController::class, 'subjectsByForm'])->name('marks.subjectsByForm');
        Route::get('marks/terms-by-form/{form}', [MarksController::class, 'termsByForm'])->name('marks.termsByForm');
        Route::get('marks/sequences-by-form-term/{form}/{term}', [MarksController::class, 'sequencesByFormTerm'])->name('marks.sequencesByFormTerm');
        Route::post('marks/save', [MarksController::class, 'save'])->name('marks.save');
        Route::post('marks/{submission}/submit', [MarksController::class, 'submit'])->name('marks.submit');
        Route::post('marks/{submission}/approve', [MarksController::class, 'approve'])->name('marks.approve');
        Route::post('marks/{submission}/return', [MarksController::class, 'returnMarks'])->name('marks.return');

        // Exam Publishing
        Route::get('exam-publishing', [ExamPublishingController::class, 'index'])->name('exam-publishing.index');
        Route::post('exam-publishing/publish-subject', [ExamPublishingController::class, 'publishSubject'])->name('exam-publishing.publish-subject');
        Route::post('exam-publishing/unpublish-subject', [ExamPublishingController::class, 'unpublishSubject'])->name('exam-publishing.unpublish-subject');
        Route::post('exam-publishing/bulk-transition', [ExamPublishingController::class, 'bulkTransition'])->name('exam-publishing.bulk-transition');
        Route::get('exam-publishing/sections-by-form/{form}', [ExamPublishingController::class, 'sectionsByForm'])->name('exam-publishing.sectionsByForm');
        Route::get('exam-publishing/terms-by-form/{form}', [ExamPublishingController::class, 'termsByForm'])->name('exam-publishing.termsByForm');
        Route::get('exam-publishing/sequences-by-form-term/{form}/{term}', [ExamPublishingController::class, 'sequencesByFormTerm'])->name('exam-publishing.sequencesByFormTerm');

        // Attendance
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');

        // Timetable / Routines
        Route::get('timetable/slots', [TimetableController::class, 'slots'])->name('timetable.slots');
        Route::post('timetable/slots', [TimetableController::class, 'storeSlot'])->name('timetable.slots.store');
        Route::put('timetable/slots/{slot}', [TimetableController::class, 'updateSlot'])->name('timetable.slots.update');
        Route::delete('timetable/slots/{slot}', [TimetableController::class, 'destroySlot'])->name('timetable.slots.destroy');
        Route::get('timetable/class-schedule', [TimetableController::class, 'classSchedule'])->name('timetable.class-schedule');
        Route::get('timetable/sections-by-form/{form}', [TimetableController::class, 'getSectionsByForm'])->name('timetable.sections-by-form');
        Route::post('timetable/save-day-schedule', [TimetableController::class, 'saveDaySchedule'])->name('timetable.save-day-schedule');
        Route::get('timetable/teacher-schedule', [TimetableController::class, 'teacherSchedule'])->name('timetable.teacher-schedule');
        Route::delete('timetable/entries/{entry}', [TimetableController::class, 'destroyEntry'])->name('timetable.entries.destroy');

        // Report Cards
        Route::get('report-cards', [ReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/sections-by-form/{form}', [ReportCardController::class, 'sectionsByForm'])->name('report-cards.sections-by-form');
        Route::post('report-cards/generate', [ReportCardController::class, 'generate'])->name('report-cards.generate');
        Route::post('report-cards/bulk-download', [ReportCardController::class, 'bulkDownload'])->name('report-cards.bulk-download');
        Route::get('report-cards/{termResult}', [ReportCardController::class, 'show'])->name('report-cards.show');
        Route::post('report-cards/publish', [ReportCardController::class, 'publish'])->name('report-cards.publish');

        // Staff / User Management
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        // Parent / Guardian Portal Management
        Route::get('parent-portal', [ParentPortalController::class, 'index'])->name('parent-portal.index');
        Route::get('parent-portal/{guardian}', [ParentPortalController::class, 'show'])->name('parent-portal.show');
        Route::put('parent-portal/{guardian}', [ParentPortalController::class, 'update'])->name('parent-portal.update');
        Route::post('parent-portal/{guardian}/invite', [ParentPortalController::class, 'invite'])->name('parent-portal.invite');
        Route::post('parent-portal/{guardian}/set-password', [ParentPortalController::class, 'setPassword'])->name('parent-portal.set-password');
        Route::post('parent-portal/{guardian}/disable', [ParentPortalController::class, 'disable'])->name('parent-portal.disable');
        Route::post('parent-portal/{guardian}/reset-password', [ParentPortalController::class, 'resetPassword'])->name('parent-portal.reset-password');

        // Parent Payment Receipt Verification
        Route::get('parent-payments', [ParentPaymentVerificationController::class, 'index'])->name('parent-payments.index');
        Route::get('parent-payments/{submission}', [ParentPaymentVerificationController::class, 'show'])->name('parent-payments.show');
        Route::post('parent-payments/{submission}/approve', [ParentPaymentVerificationController::class, 'approve'])->name('parent-payments.approve');
        Route::post('parent-payments/{submission}/reject', [ParentPaymentVerificationController::class, 'reject'])->name('parent-payments.reject');

        // PTA Management
        Route::get('pta', [PtaController::class, 'index'])->name('pta.index');
        Route::post('pta/levies', [PtaController::class, 'storeLevy'])->name('pta.levies.store');
        Route::delete('pta/levies/{levy}', [PtaController::class, 'destroyLevy'])->name('pta.levies.destroy');
        Route::post('pta/announcements', [PtaController::class, 'storeAnnouncement'])->name('pta.announcements.store');
        Route::post('pta/announcements/{announcement}/toggle', [PtaController::class, 'togglePublishAnnouncement'])->name('pta.announcements.toggle');
        Route::delete('pta/announcements/{announcement}', [PtaController::class, 'destroyAnnouncement'])->name('pta.announcements.destroy');
        Route::post('pta/meetings', [PtaController::class, 'storeMeeting'])->name('pta.meetings.store');
        Route::post('pta/meetings/{meeting}/minutes', [PtaController::class, 'uploadMinutes'])->name('pta.meetings.minutes');
        Route::post('pta/meetings/{meeting}/status', [PtaController::class, 'updateMeetingStatus'])->name('pta.meetings.status');
        Route::delete('pta/meetings/{meeting}', [PtaController::class, 'destroyMeeting'])->name('pta.meetings.destroy');

        // Settings
        Route::get('configuration-health', [ConfigurationHealthController::class, 'index'])->name('configuration-health.index');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('settings/grade-scale', [SettingsController::class, 'updateGradeScale'])->name('settings.grade-scale');
        Route::post('settings/level-mode', [SettingsController::class, 'setLevelMode'])->name('settings.level-mode');
        Route::put('settings/group/{group}', [SettingsController::class, 'updateGroup'])->name('settings.group.update');

        // Roles & Permissions
        Route::resource('roles', RoleController::class);

        // Fee Categories
        Route::resource('fee-categories', FeeCategoryController::class)->except(['show']);

        // Fee Discounts
        Route::get('fee-discounts', [FeeDiscountController::class, 'index'])->name('fee-discounts.index');
        Route::get('fee-discounts/create', [FeeDiscountController::class, 'create'])->name('fee-discounts.create');
        Route::post('fee-discounts', [FeeDiscountController::class, 'store'])->name('fee-discounts.store');
        Route::delete('fee-discounts/{feeDiscount}', [FeeDiscountController::class, 'destroy'])->name('fee-discounts.destroy');

        // Collect Fees
        Route::get('collect-fees', [FeeCollectionController::class, 'index'])->name('collect-fees.index');
        Route::post('collect-fees/store-payment', [FeeCollectionController::class, 'storePayment'])->name('collect-fees.store-payment');
        Route::get('collect-fees/receipt/{payment}', [FeeCollectionController::class, 'receipt'])->name('collect-fees.receipt');
        Route::get('collect-fees/streams-by-form/{form}', [FeeCollectionController::class, 'streamsByForm'])->name('collect-fees.streams-by-form');
        Route::get('collect-fees/sections-by-form/{form}', [FeeCollectionController::class, 'sectionsByForm'])->name('collect-fees.sections-by-form');

        // Quick Assign Fee
        Route::get('quick-assign', [FeeQuickAssignController::class, 'create'])->name('quick-assign.create');
        Route::post('quick-assign', [FeeQuickAssignController::class, 'store'])->name('quick-assign.store');
        Route::get('quick-assign/search-students', [FeeQuickAssignController::class, 'searchStudents'])->name('quick-assign.search-students');
        Route::get('quick-assign/check-fee', [FeeQuickAssignController::class, 'checkFee'])->name('quick-assign.check-fee');

        // Assignment History
        Route::get('assignment-history', [FeeAssignmentHistoryController::class, 'index'])->name('assignment-history.index');
        Route::get('assignment-history/streams-by-form/{form}', [FeeAssignmentHistoryController::class, 'streamsByForm'])->name('assignment-history.streams-by-form');
        Route::delete('assignment-history/{studentFee}', [FeeAssignmentHistoryController::class, 'destroy'])->name('assignment-history.destroy');

        // Fee Reports
        Route::get('fee-reports', [FeeReportController::class, 'index'])->name('fee-reports.index');

        // Payment Plans
        Route::get('payment-plans', [PaymentPlanController::class, 'index'])->name('payment-plans.index');
        Route::get('payment-plans/create', [PaymentPlanController::class, 'create'])->name('payment-plans.create');
        Route::get('payment-plans/student-fees/{student}', [PaymentPlanController::class, 'studentFees'])->name('payment-plans.student-fees');
        Route::post('payment-plans', [PaymentPlanController::class, 'store'])->name('payment-plans.store');
        Route::get('payment-plans/{paymentPlan}', [PaymentPlanController::class, 'show'])->name('payment-plans.show');
        Route::patch('payment-plans/{paymentPlan}/cancel', [PaymentPlanController::class, 'cancel'])->name('payment-plans.cancel');
        Route::post('payment-plans/{paymentPlan}/pay', [PaymentPlanController::class, 'pay'])->name('payment-plans.pay');

        // Bulk Student Upload
        Route::get('bulk-upload', [BulkUploadController::class, 'index'])->name('bulk-upload.index');
        Route::get('bulk-upload/template', [BulkUploadController::class, 'template'])->name('bulk-upload.template');
        Route::post('bulk-upload', [BulkUploadController::class, 'upload'])->name('bulk-upload.upload');

        // Promotion
        Route::get('promotion', [PromotionController::class, 'index'])->name('promotion.index');
        Route::post('promotion/process', [PromotionController::class, 'process'])->name('promotion.process');

        // Reports & Analytics
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');

    });

    // ═══════════════════════════════════════════════════════════════
    // Student Management — admin AND staff.
    // A SIBLING of the admin-only group above, not a child: nesting it inside
    // `role:super_admin,admin` meant the outer middleware rejected `staff`
    // before this one was ever consulted, so the carve-out never applied.
    // ═══════════════════════════════════════════════════════════════
    Route::middleware('role:super_admin,admin,staff')->prefix('admin')->name('admin.')->group(function () {
            Route::get('students/form-streams/{form}', [StudentController::class, 'getFormStreams'])->name('students.form-streams');
            Route::get('students/form-sections/{form}', [StudentController::class, 'getFormSections'])->name('students.form-sections');
            Route::resource('students', StudentController::class)->except(['destroy']);

            // Group Enrol
            Route::get('group-enrol', [GroupEnrolController::class, 'index'])->name('group-enrol.index');
            Route::post('group-enrol/enrol', [GroupEnrolController::class, 'enrol'])->name('group-enrol.enrol');
            Route::get('group-enrol/streams/{form}', [GroupEnrolController::class, 'streams'])->name('group-enrol.streams');
            Route::get('group-enrol/sections/{form}', [GroupEnrolController::class, 'sections'])->name('group-enrol.sections');
            Route::get('group-enrol/preview-subjects', [GroupEnrolController::class, 'previewSubjects'])->name('group-enrol.preview-subjects');

            // Subject Add/Drop
            Route::get('subject-add-drop', [SubjectAddDropController::class, 'index'])->name('subject-add-drop.index');
            Route::get('subject-add-drop/load/{student}', [SubjectAddDropController::class, 'loadStudent'])->name('subject-add-drop.load');
            Route::post('subject-add-drop/add', [SubjectAddDropController::class, 'addSubject'])->name('subject-add-drop.add');
            Route::post('subject-add-drop/drop', [SubjectAddDropController::class, 'dropSubject'])->name('subject-add-drop.drop');
    });
});

// ═══════════════════════════════════════════════════════════════
// Finance: Accounts (Income/Expense) + Payment Accounts
// Accessible to super_admin, admin and accountant.
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin,accountant'])->prefix('admin')->name('admin.')->group(function () {

    // Income & Expense
    Route::resource('account/income', IncomeController::class)->except(['show'])->names('account.income');
    Route::resource('account/income-category', IncomeCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->names('account.income-category');
    Route::resource('account/expense', ExpenseController::class)->except(['show'])->names('account.expense');
    Route::resource('account/expense-category', ExpenseCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->names('account.expense-category');
    Route::get('account/outcome', [OutcomeController::class, 'index'])->name('account.outcome.index');

    // Payment Accounts
    Route::resource('payment-account', PaymentAccountController::class)->except(['show']);
    Route::get('payment-account/{payment_account}/account-book', [PaymentAccountController::class, 'accountBook'])->name('payment-account.account-book');
    Route::get('payment-account/{payment_account}/deposit', [PaymentAccountController::class, 'depositForm'])->name('payment-account.deposit-form');
    Route::post('payment-account/{payment_account}/deposit', [PaymentAccountController::class, 'deposit'])->name('payment-account.deposit');
    Route::get('payment-account/{payment_account}/withdraw', [PaymentAccountController::class, 'withdrawForm'])->name('payment-account.withdraw-form');
    Route::post('payment-account/{payment_account}/withdraw', [PaymentAccountController::class, 'withdraw'])->name('payment-account.withdraw');
    Route::post('payment-account/{payment_account}/recompute', [PaymentAccountController::class, 'recompute'])->name('payment-account.recompute');
    Route::delete('payment-account/transaction/{transaction}', [PaymentAccountController::class, 'destroyTransaction'])->name('payment-account.transaction.destroy');

    // Transfers
    Route::resource('payment-account-transfer', PaymentAccountTransferController::class)->only(['index', 'create', 'store', 'destroy']);

    // Reports + deferred linking
    Route::get('payment-account-report/cashflow', [PaymentAccountReportController::class, 'cashflow'])->name('payment-account-report.cashflow');
    Route::get('payment-account-report/summary', [PaymentAccountReportController::class, 'summary'])->name('payment-account-report.summary');
    Route::get('payment-account-report/unlinked', [PaymentAccountReportController::class, 'unlinked'])->name('payment-account-report.unlinked');
    Route::post('payment-account-report/link', [PaymentAccountReportController::class, 'link'])->name('payment-account-report.link');
    Route::get('payment-account-report/statement/{payment_account}', [PaymentAccountReportController::class, 'statement'])->name('payment-account-report.statement');

    // ── Budgets ──
    Route::get('budget-dashboard', [BudgetDashboardController::class, 'index'])->name('budget-dashboard');
    Route::get('budget/{budget}/summary-json', [BudgetDashboardController::class, 'summary'])->name('budget.summary-json');

    // Budget reports
    Route::get('budget-report/performance', [BudgetReportController::class, 'performance'])->name('budget-report.performance');
    Route::get('budget-report/variance', [BudgetReportController::class, 'variance'])->name('budget-report.variance');
    Route::get('budget-report/department', [BudgetReportController::class, 'department'])->name('budget-report.department');
    Route::get('budget-report/cashflow', [BudgetReportController::class, 'cashflow'])->name('budget-report.cashflow');

    // Budget lifecycle + revise
    Route::post('budget/{budget}/submit', [BudgetController::class, 'submitForApproval'])->name('budget.submit');
    Route::post('budget/{budget}/approve', [BudgetController::class, 'approve'])->name('budget.approve');
    Route::post('budget/{budget}/activate', [BudgetController::class, 'activate'])->name('budget.activate');
    Route::post('budget/{budget}/close', [BudgetController::class, 'close'])->name('budget.close');
    Route::post('budget/{budget}/cancel', [BudgetController::class, 'cancel'])->name('budget.cancel');
    Route::post('budget/{budget}/revise', [BudgetController::class, 'revise'])->name('budget.revise');

    // Budget allocations (nested under a budget for store; shallow for update/destroy)
    Route::post('budget/{budget}/allocations', [BudgetAllocationController::class, 'store'])->name('budget.allocations.store');
    Route::get('budget/{budget}/allocations-json', [BudgetAllocationController::class, 'byBudget'])->name('budget.allocations.json');
    Route::put('budget-allocations/{allocation}', [BudgetAllocationController::class, 'update'])->name('budget.allocations.update');
    Route::delete('budget-allocations/{allocation}', [BudgetAllocationController::class, 'destroy'])->name('budget.allocations.destroy');

    // Budget CRUD (after the specific routes above so they take precedence)
    Route::resource('budget', BudgetController::class)->except(['show']);
    Route::get('budget/{budget}', [BudgetController::class, 'show'])->name('budget.show');

    // ═══════════════════════════════════════════════════════════════
    // OHADA Accounting (General Ledger)
    // ═══════════════════════════════════════════════════════════════

    // Chart of Accounts
    Route::get('chart-of-accounts/by-class', [ChartOfAccountController::class, 'getByClass'])->name('chart-of-accounts.by-class');
    Route::post('chart-of-accounts/{chart_of_account}/toggle-status', [ChartOfAccountController::class, 'toggleStatus'])->name('chart-of-accounts.toggle-status');
    Route::resource('chart-of-accounts', ChartOfAccountController::class)->except(['show']);

    // Fiscal Years & Periods
    Route::post('fiscal-years/{fiscalYear}/set-active', [FiscalYearController::class, 'setActive'])->name('fiscal-years.set-active');
    Route::post('fiscal-years/{fiscalYear}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
    Route::get('fiscal-years/{fiscalYear}/closing', [FiscalYearController::class, 'previewClosing'])->name('fiscal-years.closing');
    Route::post('fiscal-years/{fiscalYear}/reopen', [FiscalYearController::class, 'reopen'])->name('fiscal-years.reopen');
    Route::post('accounting-periods/{period}/toggle', [FiscalYearController::class, 'togglePeriod'])->name('accounting-periods.toggle');
    Route::resource('fiscal-years', FiscalYearController::class)->only(['index', 'store', 'destroy']);

    // Journal Entries
    Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
    Route::post('journal-entries/{journal_entry}/unpost', [JournalEntryController::class, 'unpost'])->name('journal-entries.unpost');
    Route::resource('journal-entries', JournalEntryController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    // Transaction Mappings (auto-posting config)
    // Staff attendance (daily register + monthly summary)
    Route::get('staff-attendance', [StaffAttendanceController::class, 'index'])->name('staff-attendance.index');
    Route::post('staff-attendance', [StaffAttendanceController::class, 'store'])->name('staff-attendance.store');
    Route::get('staff-attendance/report', [StaffAttendanceController::class, 'report'])->name('staff-attendance.report');

    // Staff leave
    Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::get('leaves/remaining', [LeaveController::class, 'remaining'])->name('leaves.remaining');
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::delete('leaves/{leave}', [LeaveController::class, 'destroy'])->name('leaves.destroy');

    Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
    Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');

    // Student credits (over-payments held on account)
    Route::get('student-credits', [StudentCreditController::class, 'index'])->name('student-credits.index');
    Route::post('student-credits/apply', [StudentCreditController::class, 'apply'])->name('student-credits.apply');

    // Late-payment penalties
    Route::get('fee-fines', [FeeFineController::class, 'index'])->name('fee-fines.index');
    Route::post('fee-fines', [FeeFineController::class, 'store'])->name('fee-fines.store');
    Route::put('fee-fines/{feeFine}', [FeeFineController::class, 'update'])->name('fee-fines.update');
    Route::delete('fee-fines/{feeFine}', [FeeFineController::class, 'destroy'])->name('fee-fines.destroy');
    Route::post('fee-fines/accrue', [FeeFineController::class, 'accrue'])->name('fee-fines.accrue');

    // Fixed assets and depreciation
    Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::post('fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
    Route::get('fixed-assets/categories', [FixedAssetController::class, 'categories'])->name('fixed-assets.categories');
    Route::post('fixed-assets/categories', [FixedAssetController::class, 'storeCategory'])->name('fixed-assets.categories.store');
    Route::post('fixed-assets/post-due', [FixedAssetController::class, 'postDue'])->name('fixed-assets.post-due');
    Route::get('fixed-assets/{fixedAsset}/schedule', [FixedAssetController::class, 'schedule'])->name('fixed-assets.schedule');
    Route::post('fixed-assets/{fixedAsset}/generate', [FixedAssetController::class, 'generate'])->name('fixed-assets.generate');
    Route::post('fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');
    Route::post('depreciation/{schedule}/post', [FixedAssetController::class, 'postPeriod'])->name('depreciation.post');

    // Accounting reports (read-only analysis)
    Route::prefix('accounting-reports')->name('accounting-reports.')->group(function () {
        Route::get('/', [AccountingReportsController::class, 'index'])->name('index');
        Route::get('receivables-aging', [AccountingReportsController::class, 'receivablesAging'])->name('receivables-aging');
        Route::get('payables-aging', [AccountingReportsController::class, 'payablesAging'])->name('payables-aging');
        Route::get('student-fee-aging', [AccountingReportsController::class, 'studentFeeAging'])->name('student-fee-aging');
        Route::get('budget-vs-actual', [AccountingReportsController::class, 'budgetVsActual'])->name('budget-vs-actual');
    });

    Route::get('account-mappings', [AccountMappingController::class, 'index'])->name('account-mappings.index');
    Route::post('account-mappings/save', [AccountMappingController::class, 'save'])->name('account-mappings.save');
    Route::get('account-mappings/unmapped', [AccountMappingController::class, 'unmapped'])->name('account-mappings.unmapped');
    Route::post('account-mappings/unmapped/post', [AccountMappingController::class, 'postUnmapped'])->name('account-mappings.post-unmapped');

    // Accounting Reports (all summed from posted journal lines)
    Route::get('accounting-reports/general-ledger', [GeneralLedgerController::class, 'index'])->name('accounting-reports.general-ledger');
    Route::get('accounting-reports/account-ledger', [GeneralLedgerController::class, 'account'])->name('accounting-reports.account-ledger');
    Route::get('accounting-reports/trial-balance', [GeneralLedgerController::class, 'trialBalance'])->name('accounting-reports.trial-balance');
    Route::get('accounting-reports/balance-sheet', [GeneralLedgerController::class, 'balanceSheet'])->name('accounting-reports.balance-sheet');
    Route::get('accounting-reports/income-statement', [GeneralLedgerController::class, 'incomeStatement'])->name('accounting-reports.income-statement');

    // ═══════════════════════════════════════════════════════════════
    // Human Resources (Staff + Payroll + Tax)
    // ═══════════════════════════════════════════════════════════════

    // Staff
    Route::resource('staff', StaffController::class);

    // Org / payroll config (simple lists)
    Route::resource('designations', DesignationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('work-shifts', WorkShiftTypeController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['work-shifts' => 'work_shift']);
    Route::resource('allowance-types', AllowanceTypeController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['allowance-types' => 'allowance_type']);
    Route::resource('deduction-types', DeductionTypeController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['deduction-types' => 'deduction_type']);

    // Tax engine
    Route::resource('tax-groups', TaxGroupController::class)->except(['show'])->parameters(['tax-groups' => 'tax_group']);
    Route::get('tax-settings', [TaxSettingController::class, 'index'])->name('tax-settings.index');
    Route::post('tax-settings', [TaxSettingController::class, 'store'])->name('tax-settings.store');
    Route::put('tax-settings/{tax_setting}', [TaxSettingController::class, 'update'])->name('tax-settings.update');
    Route::delete('tax-settings/{tax_setting}', [TaxSettingController::class, 'destroy'])->name('tax-settings.destroy');
    Route::get('tax-settings/{tax_setting}/exemptions', [TaxSettingController::class, 'exemptions'])->name('tax-settings.exemptions');
    Route::post('tax-settings/{tax_setting}/exemptions', [TaxSettingController::class, 'storeExemption'])->name('tax-settings.exemptions.store');
    Route::delete('tax-exemptions/{exemption}', [TaxSettingController::class, 'destroyExemption'])->name('tax-exemptions.destroy');
    Route::get('tax-report', [StaffTaxReportController::class, 'index'])->name('tax-report.index');

    // Payroll
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/report', [PayrollController::class, 'report'])->name('payroll.report');
    Route::get('payroll/generate/{staff}', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('payroll/generate/{staff}', [PayrollController::class, 'store'])->name('payroll.store');
    Route::post('payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
    Route::post('payroll/{payroll}/unpay', [PayrollController::class, 'unpay'])->name('payroll.unpay');
});
