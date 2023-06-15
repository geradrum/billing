<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Water\SADMBillsRequest;
use App\Http\Requests\API\V1\Water\SIAPABillRequest;
use App\Support\Water\SADM\SADM;
use App\Support\Water\SIAPA\SIAPA;
use Illuminate\Http\Request;

class WaterBillingController extends Controller
{

    public function siapaServices(SIAPABillRequest $request)
    {
        return [];
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
