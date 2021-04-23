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
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $initDates = $this->createInitialDates($startDate, $request->days);
        $reservationCreated = 0;

        try {
            DB::beginTransaction();

            foreach ($initDates as $date) {
                while ($date->lte($endDate)) {
                    if ($date->gte($startDate)) {
                        $timeDetails = $this->createTimeDetails($date, $request->from, $request->to);

                        if (!$this->isAvailableAsset($request->asset_id, $timeDetails)) {
                            return response(['errors' => __('validation.asset_reserved', ['attribute' => 'asset_id'])], Response::HTTP_UNPROCESSABLE_ENTITY);
                        }

                        $this->createReservations($request, $timeDetails);

                        $reservationCreated += 1;
                    }

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
    protected function createReservations($request, $timeDetails)
    {
        $asset = Asset::findOrFail($request->asset_id);

        $reservation = Reservation::create($request->validated() + $timeDetails + [
            'user_id_reservation' => $request->user()->uuid,
            'user_fullname' => $request->user()->name,
            'username' => $request->user()->username,
            'email' => $request->user()->email,
            'asset_name' => $asset->name,
            'asset_description' => $asset->description,
            'approval_status' => ReservationStatusEnum::already_approved()
        ]);

        event(new AfterReservation($reservation, $asset));
    }

    /**
     * Functoin to create the initial dates in a week
     *
     * @param  Date $startDate
     * @param  Array $days
     * @return Array
     */
    protected function createInitialDates($startDate, $days)
    {
        $date = $startDate->copy();

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
