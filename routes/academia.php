<?php

use App\Http\Controllers\Api\Gym\AccessController;
use App\Http\Controllers\Api\Gym\AccessDeviceController;
use App\Http\Controllers\Api\Gym\AccessPinController;
use App\Http\Controllers\Api\Gym\AuthController;
use App\Http\Controllers\Api\Gym\BiometricController;
use App\Http\Controllers\Api\Gym\DeviceController;
use App\Http\Controllers\Api\Gym\GymClassController;
use App\Http\Controllers\Api\Gym\InvoiceController;
use App\Http\Controllers\Api\Gym\MeController;
use App\Http\Controllers\Api\Gym\PaymentWebhookController;
use App\Http\Controllers\Api\Gym\PlanController;
use App\Http\Controllers\Api\Gym\PrivacyController;
use App\Http\Controllers\Api\Gym\ReportController;
use App\Http\Controllers\Api\Gym\StaffUserController;
use App\Http\Controllers\Api\Gym\StudentController;
use App\Http\Controllers\Api\Gym\SubscriptionController;
use App\Http\Controllers\Api\Gym\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| App Academia API — /api/academia/v1
|--------------------------------------------------------------------------
|
| REST API consumed by the gym app (web/mobile), the turnstiles and the
| payment gateway. Isolated from MK Gestão (own guard, tables and tokens).
|
*/

Route::name('academia.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:gym-login')->name('auth.login');
    Route::post('webhooks/payments', PaymentWebhookController::class)->middleware('throttle:gym-device')->name('webhooks.payments');

    Route::prefix('devices')->name('devices.')->middleware(['gym.device', 'throttle:gym-device'])->group(function (): void {
        Route::post('access-check', [DeviceController::class, 'accessCheck'])->name('access-check');
        Route::post('events', [DeviceController::class, 'storeEvents'])->name('events');
        Route::get('sync', [DeviceController::class, 'sync'])->name('sync');
        Route::post('heartbeat', [DeviceController::class, 'heartbeat'])->name('heartbeat');
    });

    Route::middleware(['auth:gym', 'throttle:gym-api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

        Route::prefix('me')->name('me.')->middleware('gym.role:aluno')->group(function (): void {
            Route::get('/', [MeController::class, 'profile'])->name('profile');
            Route::get('invoices', [MeController::class, 'invoices'])->name('invoices');
            Route::post('invoices/{invoice}/charge', [MeController::class, 'chargeInvoice'])->name('invoices.charge');
            Route::put('access-pin', [AccessPinController::class, 'updateOwn'])->name('access-pin');
            Route::get('access-events', [MeController::class, 'accessEvents'])->name('access-events');
            Route::get('personal-data', [PrivacyController::class, 'exportOwn'])->name('personal-data');
            Route::delete('biometric-consent', [MeController::class, 'revokeBiometricConsent'])->name('biometric-consent.revoke');
        });

        Route::middleware('gym.role:gestor,administrador')->group(function (): void {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');
            Route::get('reports/delinquency', [ReportController::class, 'delinquency'])->name('reports.delinquency');
            Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
            Route::get('reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');

            Route::get('units', [UnitController::class, 'index'])->name('units.index');

            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::post('students', [StudentController::class, 'store'])->name('students.store');
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
            Route::patch('students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::post('students/{student}/guardians', [StudentController::class, 'storeGuardian'])->name('students.guardians.store');
            Route::post('students/{student}/portal-access', [StudentController::class, 'grantPortalAccess'])->name('students.portal-access');
            Route::put('students/{student}/access-pin', [AccessPinController::class, 'updateForStudent'])->name('students.access-pin');

            Route::post('students/{student}/biometric-consent', [BiometricController::class, 'grantConsent'])->name('students.biometric-consent.store');
            Route::delete('students/{student}/biometric-consent', [BiometricController::class, 'revokeConsent'])->name('students.biometric-consent.destroy');
            Route::post('students/{student}/face-enrollment', [BiometricController::class, 'enrollFace'])->name('students.face-enrollment.store');
            Route::delete('students/{student}/face-enrollment', [BiometricController::class, 'removeFace'])->name('students.face-enrollment.destroy');

            Route::get('students/{student}/subscriptions', [SubscriptionController::class, 'index'])->name('students.subscriptions.index');
            Route::post('students/{student}/subscriptions', [SubscriptionController::class, 'store'])->name('students.subscriptions.store');
            Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay');
            Route::post('invoices/{invoice}/charge', [InvoiceController::class, 'charge'])->name('invoices.charge');

            Route::get('classes', [GymClassController::class, 'index'])->name('classes.index');
            Route::post('classes', [GymClassController::class, 'store'])->name('classes.store');
            Route::get('classes/{class}', [GymClassController::class, 'show'])->name('classes.show');
            Route::patch('classes/{class}', [GymClassController::class, 'update'])->name('classes.update');
            Route::post('classes/{class}/students', [GymClassController::class, 'enroll'])->name('classes.students.store');
            Route::delete('classes/{class}/students/{student}', [GymClassController::class, 'unenroll'])->name('classes.students.destroy');
            Route::get('classes/{class}/attendances', [GymClassController::class, 'attendance'])->name('classes.attendances.index');
            Route::post('classes/{class}/attendances', [GymClassController::class, 'recordAttendance'])->name('classes.attendances.store');

            Route::get('access/events', [AccessController::class, 'events'])->name('access.events');
            Route::get('access/occupancy', [AccessController::class, 'occupancy'])->name('access.occupancy');
            Route::post('access/manual-release', [AccessController::class, 'manualRelease'])->name('access.manual-release');
        });

        Route::middleware('gym.role:administrador')->group(function (): void {
            Route::post('units', [UnitController::class, 'store'])->name('units.store');
            Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');

            Route::get('staff', [StaffUserController::class, 'index'])->name('staff.index');
            Route::post('staff', [StaffUserController::class, 'store'])->name('staff.store');
            Route::patch('staff/{staffUser}', [StaffUserController::class, 'update'])->name('staff.update');

            Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
            Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');

            Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('payments/{payment}/refund', [InvoiceController::class, 'refund'])->name('payments.refund');

            Route::get('students/{student}/personal-data', [PrivacyController::class, 'export'])->name('students.personal-data');
            Route::post('students/{student}/anonymize', [PrivacyController::class, 'anonymize'])->name('students.anonymize');

            Route::get('access-devices', [AccessDeviceController::class, 'index'])->name('access-devices.index');
            Route::post('access-devices', [AccessDeviceController::class, 'store'])->name('access-devices.store');
            Route::patch('access-devices/{device}', [AccessDeviceController::class, 'update'])->name('access-devices.update');
            Route::post('access-devices/{device}/rotate-token', [AccessDeviceController::class, 'rotateToken'])->name('access-devices.rotate-token');
        });
    });
});
