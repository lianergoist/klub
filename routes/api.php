<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TrainerController;
use Illuminate\Support\Facades\Route;

Route::post('site-access', [\App\Http\Controllers\GateController::class, 'appToken'])->middleware('throttle:10,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::get('demo-accounts', [AuthController::class, 'demoAccounts']);
Route::post('demo-login', [AuthController::class, 'demoLogin'])->middleware('throttle:30,1');
Route::get('packages/offer', [\App\Http\Controllers\Api\PackageController::class, 'offer']);
Route::get('guest/offers', [GuestController::class, 'offers']);
Route::get('login-links', [GuestController::class, 'loginLinks']);
Route::post('guest/book', [GuestController::class, 'book'])->middleware('throttle:10,1');
Route::get('punchcards', [\App\Http\Controllers\Api\PunchcardController::class, 'offer']);
Route::post('punchcards/signup', [\App\Http\Controllers\Api\PunchcardController::class, 'signup'])->middleware('throttle:10,1');
Route::get('feed/images/{image}', [FeedController::class, 'image'])->name('feed.image')->middleware('signed');
Route::get('expense-claims/{claim}/file', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'file'])->name('expense.file')->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'show']);
    Route::post('logout', [AuthController::class, 'logout']);
    // Også for gæster: se og betal egen regning
    Route::get('invoices/{invoice}', [FinanceController::class, 'invoice']);
    Route::post('invoices/{invoice}/pay', [FinanceController::class, 'pay']);
    Route::get('guest/status', [GuestController::class, 'status']);
    // Klippekort: gæstens egen side, køb og tilmelding med klip
    Route::get('klip', [\App\Http\Controllers\Api\PunchcardController::class, 'home']);
    Route::post('klip/buy', [\App\Http\Controllers\Api\PunchcardController::class, 'buy']);
    Route::post('klip/activities/{activity}/signup', [\App\Http\Controllers\Api\PunchcardController::class, 'punch']);
    Route::post('klip/activities/{activity}/cancel', [\App\Http\Controllers\Api\PunchcardController::class, 'unpunch']);

    // Resten kun for medlemmer
    Route::middleware(\App\Http\Middleware\EnsureMember::class)->group(function () {

    Route::post('devices', [\App\Http\Controllers\Api\DeviceController::class, 'store']);
    Route::delete('devices', [\App\Http\Controllers\Api\DeviceController::class, 'destroy']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::get('profile/packages', [\App\Http\Controllers\Api\PackageController::class, 'mine']);
    Route::post('profile/purchases', [\App\Http\Controllers\Api\PackageController::class, 'buy']);
    Route::post('profile/purchases/{purchase}/cancel', [\App\Http\Controllers\Api\PackageController::class, 'cancel']);
    Route::get('profile/categories', [ProfileController::class, 'categories']);
    Route::put('profile/categories', [ProfileController::class, 'saveCategories']);
    Route::get('profile/invoices', [ProfileController::class, 'invoices']);
    Route::get('profile/export', [ProfileController::class, 'export']);
    Route::get('settings/packages', [\App\Http\Controllers\Api\PackageController::class, 'admin']);
    Route::post('settings/packages', [\App\Http\Controllers\Api\PackageController::class, 'store']);
    Route::put('settings/packages/{package}', [\App\Http\Controllers\Api\PackageController::class, 'update']);
    Route::put('settings/packages/{package}/archive', [\App\Http\Controllers\Api\PackageController::class, 'archive']);
    Route::post('settings/packages/{package}/price', [\App\Http\Controllers\Api\PackageController::class, 'setPrice']);
    Route::delete('settings/packages/{package}/price/{price}', [\App\Http\Controllers\Api\PackageController::class, 'deletePrice']);
    Route::post('settings/person-categories', [\App\Http\Controllers\Api\PackageController::class, 'saveCategory']);
    Route::put('settings/person-categories/{category}', [\App\Http\Controllers\Api\PackageController::class, 'saveCategory']);
    Route::get('notifications', [ProfileController::class, 'notifications']);

    Route::get('feed', [FeedController::class, 'index']);
    Route::post('feed', [FeedController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('feed/{post}', [FeedController::class, 'destroy']);
    Route::post('feed/{post}/like', [FeedController::class, 'like']);
    Route::post('feed/{post}/comments', [FeedController::class, 'comment'])->middleware('throttle:30,1');
    Route::post('feed/{post}/report', [FeedController::class, 'report']);
    Route::put('feed/{post}/moderate', [FeedController::class, 'moderate']);
    Route::post('feed/{post}/dismiss', [FeedController::class, 'dismissReports']);
    Route::delete('feed-comments/{comment}', [FeedController::class, 'deleteComment']);
    Route::put('feed-comments/{comment}/moderate', [FeedController::class, 'moderateComment']);

    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/unread', [MessageController::class, 'unread']);
    Route::get('messages/people', [MessageController::class, 'people']);
    Route::post('messages/start', [MessageController::class, 'start']);
    Route::get('messages/{conversation}', [MessageController::class, 'show']);
    Route::post('messages/{conversation}', [MessageController::class, 'send'])->middleware('throttle:30,1');
    Route::post('messages/{conversation}/members', [MessageController::class, 'addMembers']);
    Route::put('messages/{conversation}/title', [MessageController::class, 'rename']);
    Route::post('messages/{conversation}/leave', [MessageController::class, 'leave']);
    Route::post('message-items/{message}/report', [MessageController::class, 'report']);
    Route::post('users/{user}/block', [MessageController::class, 'block']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);

    Route::get('expense-claims', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'mine']);
    Route::post('expense-claims', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'store'])->middleware('throttle:10,1');

    Route::get('activities', [ActivityController::class, 'index']);
    Route::post('activities', [ActivityController::class, 'store']);
    Route::get('activities/{activity}', [ActivityController::class, 'show']);
    Route::put('activities/{activity}', [ActivityController::class, 'update']);
    Route::put('activities/{activity}/trainers', [ActivityController::class, 'setTrainers']);
    Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel']);
    Route::post('activities/{activity}/take', [ActivityController::class, 'take']);
    Route::post('activities/{activity}/leave', [ActivityController::class, 'leave']);
    Route::put('activities/{activity}/attendance', [ActivityController::class, 'attendance']);
    Route::post('activities/{activity}/signup', [ActivityController::class, 'signup']);
    Route::post('activities/{activity}/unsignup', [ActivityController::class, 'cancelSignup']);
    Route::get('trainer-options', [ActivityController::class, 'trainerOptions']);
    Route::post('activities/{activity}/activate', [ActivityController::class, 'activate']);

    Route::get('equipment', [\App\Http\Controllers\Api\EquipmentController::class, 'index']);
    Route::post('equipment', [\App\Http\Controllers\Api\EquipmentController::class, 'store']);
    Route::get('equipment/availability', [\App\Http\Controllers\Api\EquipmentController::class, 'availability']);
    Route::get('equipment/{equipment}', [\App\Http\Controllers\Api\EquipmentController::class, 'show']);
    Route::put('equipment/{equipment}', [\App\Http\Controllers\Api\EquipmentController::class, 'update']);
    Route::post('equipment/{equipment}/status', [\App\Http\Controllers\Api\EquipmentController::class, 'setStatus']);
    Route::post('equipment/{equipment}/archive', [\App\Http\Controllers\Api\EquipmentController::class, 'archive']);
    Route::post('equipment-types', [\App\Http\Controllers\Api\EquipmentController::class, 'storeType']);
    Route::put('equipment-types/{type}', [\App\Http\Controllers\Api\EquipmentController::class, 'updateType']);
    Route::get('equipment-bookings', [\App\Http\Controllers\Api\EquipmentController::class, 'bookings']);
    Route::post('equipment-bookings', [\App\Http\Controllers\Api\EquipmentController::class, 'book'])->middleware('throttle:30,1');
    Route::post('equipment-bookings/{booking}/cancel', [\App\Http\Controllers\Api\EquipmentController::class, 'cancel']);
    Route::post('equipment-approvals/{approval}/decide', [\App\Http\Controllers\Api\EquipmentController::class, 'decide']);
    Route::post('equipment-moves/{move}/decline', [\App\Http\Controllers\Api\EquipmentController::class, 'replyMove']);
    Route::post('activities/{activity}/move-requests', [\App\Http\Controllers\Api\ActivityController::class, 'requestMoves'])->middleware('throttle:20,1');

    Route::get('unavailability', [TrainerController::class, 'unavailability']);
    Route::post('unavailability', [TrainerController::class, 'addUnavailability']);
    Route::delete('unavailability/{unavailability}', [TrainerController::class, 'deleteUnavailability']);
    Route::get('availability', [TrainerController::class, 'availability']);

    Route::get('members', [MemberController::class, 'index']);
    Route::get('members/export', [MemberController::class, 'export']);
    Route::get('members/{member}', [MemberController::class, 'show']);
    Route::put('members/{member}', [MemberController::class, 'update']);
    Route::post('members/{member}/approve', [MemberController::class, 'approve']);
    Route::post('members/{member}/roles', [MemberController::class, 'addRole']);
    Route::put('roles/{role}/end', [MemberController::class, 'endRole']);
    Route::post('members/{member}/permissions', [\App\Http\Controllers\Api\PermissionController::class, 'grant']);
    Route::put('user-permissions/{grant}/end', [\App\Http\Controllers\Api\PermissionController::class, 'end']);
    Route::get('settings/audit', [\App\Http\Controllers\Api\AuditController::class, 'index']);
    Route::get('settings/permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index']);
    Route::put('settings/permissions/{permission}/members', [\App\Http\Controllers\Api\PermissionController::class, 'members']);
    Route::put('settings/permissions/{permission}', [\App\Http\Controllers\Api\PermissionController::class, 'update']);

    Route::prefix('finance')->group(function () {
        Route::get('invoices', [FinanceController::class, 'invoices']);
        Route::get('expense-claims', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'index']);
        Route::get('expense-claims/last-account', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'lastAccount']);
        Route::post('expense-claims/{claim}/pay', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'pay']);
        Route::post('expense-claims/{claim}/approve', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'approve']);
        Route::post('expense-claims/{claim}/reject', [\App\Http\Controllers\Api\ExpenseClaimController::class, 'reject']);
        Route::post('invoices/{invoice}/manual', [FinanceController::class, 'manualPayment']);
        Route::post('invoices/{invoice}/refund', [FinanceController::class, 'refund']);
        Route::post('invoices/{invoice}/remind', [FinanceController::class, 'remind']);
        Route::post('membership/generate', [FinanceController::class, 'generateMembership']);
        Route::get('accounts', [FinanceController::class, 'accounts']);
        Route::get('overview', [FinanceController::class, 'overview']);
        Route::get('menu', [FinanceController::class, 'menu']);
        Route::post('invoices/remind-overdue', [FinanceController::class, 'remindOverdue']);
        Route::put('budget', [FinanceController::class, 'saveBudget']);
        Route::get('entries', [FinanceController::class, 'entries']);
        Route::get('entries/export', [FinanceController::class, 'exportEntries']);
        Route::post('entries', [FinanceController::class, 'storeEntry']);
        Route::post('entries/{entry}/reverse', [FinanceController::class, 'reverseEntry']);
        Route::get('entries/{entry}/attachment', [FinanceController::class, 'attachment']);
        Route::post('reconcile', [FinanceController::class, 'reconcile']);
        Route::get('prices', [FinanceController::class, 'prices']);
        Route::post('prices', [FinanceController::class, 'setPrice']);
        Route::delete('prices/{price}', [FinanceController::class, 'deletePrice']);
        Route::get('payouts', [FinanceController::class, 'payouts']);
        Route::post('payouts/fetch', [FinanceController::class, 'fetchPayouts']);
        Route::get('payouts/{payout}', [FinanceController::class, 'payout']);
        Route::get('audit', [FinanceController::class, 'auditLog']);
    });
    });
});
