<?php

namespace Devrahul\Stripepayintegration\Http\Controllers\API\v1;
use App\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(){
        $plans = Plan::all();
        return view('plans.index', compact('plans'));
    }

    public function show(Plan $plan, Request $request)
    {
        return view('plans.show', compact('plan'));
    }
}



