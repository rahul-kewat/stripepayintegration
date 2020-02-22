<?php


namespace Devrahul\Stripepayintegration\Http\Controllers\API\v1;

use Illuminate\Http\Request;
use App\Plan;

class SubscriptionController extends Controller
{
    public function create(Request $request, Plan $plan)
    {
        $plan = Plan::findOrFail($request->get('plan'));
        
        $request->user()
            ->newSubscription('main', $plan->stripe_plan)
            ->create($request->stripeToken);
        
        return redirect()->route('home')->with('success', 'Your plan subscribed successfully');
    }
}