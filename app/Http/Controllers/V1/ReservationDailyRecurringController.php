<?php

namespace App\Http\Controllers\V1;

use App\Events\AfterReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRecurringRequest;
use App\Models\Asset;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReservationDailyRecurringController extends Controller
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->authorizeResource(Reservation::class);
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ReservationRecurringRequest $request)
    {
        $date = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $reservationCreated = 0;

        try {
            DB::beginTransaction();

            while ($date->lte($endDate)) {
                $timeDetails = $this->createTimeDetails($date, $request->from, $request->to);

                if (!$this->isAvailableAsset($request->asset_id, $timeDetails)) {
                    return response(['errors' => __('validation.asset_reserved', ['attribute' => 'asset_id'])], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $reservationCreated += $this->createReservation($request, $timeDetails);

                $date->addDays(1);
            }

            if ($reservationCreated === 0) {
                return response(['errors' => __('message.no_reservation')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response(['message' => 'internal_server_error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response(null, Response::HTTP_CREATED);
    }

    /**
     * Method to create reservation
     *
     * @param  Request $request
     * @param  Array $timeDetails
     * @param  Int $count
     * @return Int
     */
    protected function createReservation($request, $timeDetails, $count = 0)
    {
        $asset = Asset::findOrFail($request->asset_id);

        $date = Carbon::parse($timeDetails['date']);

        if (in_array($date->dayOfWeek, $request->days)) {
            $reservation = $this->storeReservation($request, $asset, $timeDetails);

            event(new AfterReservation($reservation, $asset));

            $count += 1;
        }

        return $count;
    }
}
