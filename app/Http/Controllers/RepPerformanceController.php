<?php

namespace App\Http\Controllers;

use App\Http\Resources\RepPerformanceResource;
use App\Reports\RepPerformanceReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepPerformanceController extends Controller
{
    public function __invoke(Request $request, RepPerformanceReport $report): AnonymousResourceCollection
    {
        return RepPerformanceResource::collection($report->for($request->user()));
    }
}
