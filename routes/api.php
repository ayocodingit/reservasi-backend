<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::get('/', 'HomeController');
Route::group(['namespace' => 'V1'], function () {
    Route::get('command-center-availability', 'CommandCenterAvailabilityController')->name('command-center-availability');
    Route::apiResource('public/command-center-reservation', 'CommandCenterReservationPublicController')
        ->only('store');
    Route::get('public/command-center-reservation/{reservation:reservation_code}', 'CommandCenterReservationPublicController@show');
    Route::get('close-days/dates', 'CommandCenterCloseDaysListController');
    Route::apiResource('close-days', 'CommandCenterCloseDateController')
        ->only(['index', 'show']);
    Route::apiResource('command-center-shift', 'CommandCenterShiftController')
        ->only('index');
});



Route::group(['middleware' => ['auth:api']], function () {
    Route::get('user', 'Settings\ProfileController@index')->name('user.get');
    Route::group(['namespace' => 'V1'], function () {
        Route::get('asset/list', 'ActiveListAssetController@index')->name('asset.list');
        Route::get('reservation/list', 'ReservationListAllController')->name('reservation.list');
        Route::apiResource('asset', 'AssetController');
        Route::apiResource('reservation', 'ReservationController');
        Route::post('reservation/daily', 'ReservationDailyRecurringController')->name('reservation.daily');
        Route::post('reservation/weekly', 'ReservationWeeklyRecurringController')->name('reservation.weekly');
        Route::put('command-center-reservation/{command_center_reservation}/approval', 'CommandCenterReservationApprovalController');
        Route::apiResource('command-center-reservation', 'CommandCenterReservationController');
        Route::apiResource('command-center-shift', 'CommandCenterShiftController')
            ->except('index');
        Route::apiResource('close-days', 'CommandCenterCloseDateController')
            ->only(['store', 'update', 'destroy']);
        Route::apiResource('reserved', 'ReservedController')
            ->only(['index', 'update'])
            ->parameters([
                'reserved' => 'reservation',
            ]);
        Route::get('dashboard/reservation-statistic', 'DashboardController@reservationStatistic')->name('reservation.dashboard');
    });
});
