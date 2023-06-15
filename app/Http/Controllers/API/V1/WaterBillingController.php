<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Water\SADMBillsRequest;
use App\Http\Requests\API\V1\Water\SIAPABillRequest;
use App\Support\Billing\Water\SADM\SADM;
use App\Support\Billing\Water\SIAPA\SIAPA;

class WaterBillingController extends Controller
{

    public function siapaServices(SIAPABillRequest $request)
    {
        return (new SIAPA($request['user'], $request['password']))->getServices();
    }

    public function siapaBill(SIAPABillRequest $request)
    {
        return (new SIAPA($request['user'], $request['password']))->getBill();
    }

    public function sadmServices(SADMBillsRequest $request)
    {
        return (new SADM($request['user'], $request['password']))->getServices();
    }

    public function sadmBill(SADMBillsRequest $request)
    {
        return (new SADM($request['user'], $request['password']))->getBills();
    }
}
