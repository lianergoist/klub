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
Route::get('membership-types', [FinanceController::class, 'membershipTypes']);
Route::get('guest/offers', [GuestController::class, 'offers']);
Route::post('guest/book', [GuestController::class, 'book'])->middleware('throttle:10,1');
Route::get('feed/images/{image}', [FeedController::class, 'image'])->name('feed.image')->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'show']);
    Route::post('logout', [AuthController::class, 'logout']);
    // Også for gæster: se og betal egen regning
    Route::get('invoices/{invoice}', [FinanceController::class, 'invoice']);
    Route::post('invoices/{invoice}/pay', [FinanceController::class, 'pay']);
    Route::get('guest/status', [GuestController::class, 'status']);

    // Resten kun for medlemmer
    Route::middleware(\App\Http\Middleware\EnsureMember::class)->group(function () {

    Route::post('devices', [\App\Http\Controllers\Api\DeviceController::class, 'store']);
    Route::delete('devices', [\App\Http\Controllers\Api\DeviceController::class, 'destroy']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/household', [ProfileController::class, 'addMember']);
    Route::delete('profile/household/upgrade', [ProfileController::class, 'cancelUpgrade']);
    Route::get('profile/categories', [ProfileController::class, 'categories']);
    Route::put('profile/categories', [ProfileController::class, 'saveCategories']);
    Route::get('profile/invoices', [ProfileController::class, 'invoices']);
    Route::get('profile/export', [ProfileController::class, 'export']);
    Route::get('profile/products', [ProfileController::class, 'products']);
    Route::post('products/{product}/order', [ProfileController::class, 'order']);
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

    Route::get('activities', [ActivityController::class, 'index']);
    Route::post('activities', [ActivityController::class, 'store']);
    Route::get('activities/{activity}', [ActivityController::class, 'show']);
    Route::put('activities/{activity}', [ActivityController::class, 'update']);
    Route::put('activities/{activity}/trainers', [ActivityController::class, 'setTrainers']);
    Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel']);
    Route::put('activities/{activity}/attendance', [ActivityController::class, 'attendance']);
    Route::post('activities/{activity}/signup', [ActivityController::class, 'signup']);
    Route::post('activities/{activity}/unsignup', [ActivityController::class, 'cancelSignup']);
    Route::get('trainer-options', [ActivityController::class, 'trainerOptions']);

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

    Route::prefix('finance')->group(function () {
        Route::get('invoices', [FinanceController::class, 'invoices']);
        Route::post('invoices/{invoice}/manual', [FinanceController::class, 'manualPayment']);
        Route::post('invoices/{invoice}/refund', [FinanceController::class, 'refund']);
        Route::post('invoices/{invoice}/remind', [FinanceController::class, 'remind']);
        Route::post('membership/generate', [FinanceController::class, 'generateMembership']);
        Route::get('accounts', [FinanceController::class, 'accounts']);
        Route::get('overview', [FinanceController::class, 'overview']);
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
