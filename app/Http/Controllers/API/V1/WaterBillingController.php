<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Water\SIAPABillRequest;
use App\Support\Water\SIAPA\SIAPA;
use Illuminate\Http\Request;

class WaterBillingController extends Controller
{

    public function siapa(SIAPABillRequest $request)
    {
        return (new SIAPA($request['user'], $request['password']))->getBill();
    }

}
