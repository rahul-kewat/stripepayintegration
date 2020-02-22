<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Coupon;
use App\Http\Requests\ValidateCoupon;
use Illuminate\Support\Carbon;
use DB;

class CouponController extends Controller
{
    protected $response = [
        'status' => 0,
        'message' => '',
    ];

    public function __construct() {
        
        $this->response['data'] = new \stdClass();
    }

    protected function setData($complexObject)
    {
        $json = json_encode($complexObject);
        $encodedString = preg_replace('/null/', '" "' , $json);
        $this->response['data'] = json_decode($encodedString);
        return $this->response['data'];
    }

    /**
     * @SWG\Get(
     *     path="/coupons",
     *     tags={"Coupons"},
     *     summary="Get coupons list",
     *     description="Get coupons list using Api",
     *     operationId="getCoupon",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="page",
     *         in="query",
     *         description="pagination page number",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         in="query",
     *         description="number of records in list",
     *         type="integer"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function getCoupons(Request $request){
        try{
            
            $query = Coupon::where('status',1);
            if($request['limit'] && $request['page']){
                $query->offset($request['limit']*$request['page'])
                      ->limit($request['limit']);
            }
                              
            $coupons = $query->get();
            $coupons = $coupons->reject(function ($coupon) {
                
                if($coupon->endDateTime != ' ' && $coupon->endDateTime < Carbon::now()->toDateTimeString()){
                    return $coupon->endDateTime;
                }
                elseif($coupon->maxUseCustomer != 0 && $coupon->maxTotalUse < $coupon->maxUseCustomer)
                {
                    return $coupon->maxTotalUse;
                }
               
            });

            if(!$coupons->isEmpty()){
                $this->response['message'] = trans('api/service.total_record');
                $this->response['status'] = 1;
                $couponReturn = [];
                foreach($coupons as $coupon){
                    array_push($couponReturn,$coupon);
                }
                $this->setData($couponReturn);
            }
            else{
                $this->setData($coupons);
                $this->response['message'] = trans('api/service.no_record_found');
                $this->response['status'] = 0;
            }
            return response()->json($this->response, 200);
            
        }
        catch (\Exception $ex) {
        
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Post(
     *     path="/validate_coupon",
     *     tags={"Coupons"},
     *     summary="Validate coupons list",
     *     description="Get validate coupons using Api",
     *     operationId="validateCoupon",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Validate Coupon Object",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *              property="code",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="price",
     *              type="string"
     *             )
     *         )
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function validateCoupon(ValidateCoupon $request){
        try{

            $price = $request->price;
            $coupon = Coupon::where('code',$request->code)
                             ->first();
            $type = $coupon->type;
            switch($type){
                case 1:
                    $price = $price - $coupon->discount;
                    break;
                case 2:
                    $price = ($price * $coupon->discount) / 100;
                    break;
                default:
                    $price = $price;
            }
            $coupons = Coupon::where('status',1)->get();
            $this->response['status'] = 1;
            $this->response['message'] = trans('api/service.coupon_added');
            $this->response['data'] = ['price' =>  $price];
            return response()->json($this->response, 200);
        }
        catch (\Exception $ex) {
            
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }
}
