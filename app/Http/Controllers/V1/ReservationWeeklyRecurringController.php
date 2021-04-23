<?php

namespace App\Http\Controllers\V1;

use App\Enums\ReservationStatusEnum;
use App\Events\AfterReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRecurringRequest;
use App\Models\Asset;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReservationWeeklyRecurringController extends Controller
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
        $endDate = Carbon::parse($request->end_date);

        $initDates = $this->createInitialDates($request);
        $reservationCreated = 0;

        try {
            DB::beginTransaction();

            foreach ($initDates as $date) {
                while ($date->lte($endDate)) {
                    $timeDetails = $this->createTimeDetails($date, $request->from, $request->to);

                    if (!$this->isAvailableAsset($request->asset_id, $timeDetails)) {
                        return response(['errors' => __('validation.asset_reserved', ['attribute' => 'asset_id'])], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }

                    $reservationCreated += $this->createReservation($request, $timeDetails);

                    $date->addWeeks($request->week);
                }
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
     * Method to create a reservation
     *
     * @param  Request $request
     * @param  Array $timeDetails
     * @param  Int $count
     * @return Int
     */
    protected function createReservation($request, $timeDetails, $count = 0)
    {
        $asset = Asset::findOrFail($request->asset_id);

        $startDate = Carbon::parse($request->start_date);
        $date = Carbon::parse($timeDetails['date']);

        if ($date->gte($startDate)) {
            $reservation = $this->storeReservation($request, $asset, $timeDetails);

            event(new AfterReservation($reservation, $asset));

            $count += 1;
        }

        return $count;
    }

    /**
     * Functoin to create the initial dates in a week
     *
     * @param  Date $startDate
     * @param  Array $days
     * @return Array
     */
    protected function createInitialDates($request)
    {
        $date = Carbon::parse($request->start_date)->copy();
        $days = $request->days;

        // Monday as the first day in a week
        $date->subDays($date->dayOfWeek - 1);

        $initDates = [];

        while (count($initDates) < count($days)) {
            if (in_array($date->dayOfWeek, $days)) {
                array_push($initDates, $date->copy());
            }

            $date->addDays(1);
        }

        return $initDates;
    }
}
