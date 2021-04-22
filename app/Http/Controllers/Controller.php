<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Method to create time details
     *
     * @return Array
     */
    protected function createTimeDetails($date, $from, $to)
    {
        $date = $date->format('Y-m-d');

        return [
            'date' => $date,
            'start_time' => Carbon::parse($date . $from),
            'end_time' => Carbon::parse($date . $to)
        ];
    }

    /**
     * Function to check asset availability
     *
     * @param  [String] $asset_id
     * @param  [Array] $timeDetails
     * @return Boolean
     */
    protected function isAvailableAsset($asset_id, $timeDetails)
    {
        return Reservation::where('asset_id', $asset_id)
            ->validateTime((object) $timeDetails)
            ->alreadyApproved()
            ->doesntExist();
    }
}
