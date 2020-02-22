<?php

namespace Devrahul\Stripepayintegration\Http\Controllers\API\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\AddCard;
use App\Card;
use Stripe\Stripe;

class CardController extends Controller
{
    protected $response = [
        'status' => 0,
        'message' => '',
    ];

    public function __construct() {
        
        $this->response['data'] = new \stdClass();
    }

        
    /**
     * @SWG\Get(
     *     path="/card",
     *     tags={"Card"},
     *     summary="Get user card list",
     *     description="Get user cards detail for payments",
     *     operationId="getCard",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function getCard(Request $request){
        try{
            Stripe::setApiKey(env("STRIPE_SECRET"));
            $user = Auth::user();
            if($user->stripe_custmer_id){
                $card = \Stripe\Customer::allSources(
                    $user->stripe_custmer_id
                );

                if($card->isEmpty()){
                    $this->response['status'] = 0;
                    $this->response['message'] = trans('api/service.no_record_found');
                    $this->response['data'] = [];
                    return response()->json($this->response, 200);
                }
            
                $this->response['status'] = 1;
                $this->response['message'] = trans('api/service.total_record');
                $this->response['data'] = $card['data'];
                return response()->json($this->response, 200);
            }

        }
        catch (\Exception $ex) {
            
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

}
