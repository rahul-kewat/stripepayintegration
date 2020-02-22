<?php

namespace Devrahul\Stripepayintegration\Http\Controllers\API\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Service;
use App\ServiceCategory;
use App\slot;
use App\BookedSlot;
use App\User;
use App\Review;
use App\venderSlot;
use App\UserAddresses;
use App\Transaction;
use App\Booking;
use App\ExtraHour;
use App\PaymentSetting;
use App\BookingDetail;
use App\BookingReports;
use App\BookingDoctorReport;
use App\Notification;
use App\Services\PushNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Booking as BookingResource;
use App\Http\Resources\venderBookingList;
use App\Http\Resources\venderBookingListCollection;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\Invoice as InvoiceResource;
use App\Http\Resources\NotificationCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\BookingHistoryCollection;
use App\Http\Requests\Booking as BookingRequest;
use Stripe\Stripe;
use App\DeviceDetails;
use Braintree\Transaction as BrainTreeTransaction;
use App\Http\Requests\AddReview;
use App\Http\Requests\DoctorReport;
use App\Http\Requests\UpdateStatus;
use App\BookingRefund;

class BookingController extends Controller {

    protected $response = [
        'status' => 0,
        'message' => '',
    ];

    const totalRow = 20;
    //const cancelation_time = 86400;
    const cancelation_time = 300;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    protected $service;
    protected $pageLimit;

    public function __construct(Service $service) {
        $this->service = $service;
        $this->pageLimit = config('settings.pageLimit');
        $this->response['data'] = new \stdClass();
    }

    /**
     * @SWG\Post(
     *     path="/appointment",
     *     tags={"Booking"},
     *     summary="Create Appointment",
     *     description="Create Appointment For Docters",
     *     operationId="createAppointment",
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Create Appointment object",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *              property="vender_id",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="service_id",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="vendor_slot_id",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="price",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="booking_date",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="card_source_id",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="appointment_for",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="age",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="blood_group",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="contact_number",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="description",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="patient_name",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="report_ids",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="card_save",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="gender",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="currency",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="original_amount",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="coupon_code",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="card_id",
     *              type="string"
     *             )
     *         )    
     *     ),
     *     @SWG\Parameter(
     *         name="Authorization",
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="Language",
     *         in="header",
     *         description="Language",
     *         type="string"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function bookService(BookingRequest $request) {
        
        try{
            /** Start Transaction here while create users **/
            DB::beginTransaction();

            Stripe::setApiKey(env("STRIPE_SECRET"));

            $user = Auth::user();
            $stripeCustomerId = $user->stripe_custmer_id;
            $inputData = $request->all();

            if(!$stripeCustomerId){

                $customerDetails = \Stripe\Customer::create([
                    'description' => 'New Customer added '.$user->firstname,
                    'email' => $user->email,
                    'source' => $inputData['card_source_id']
                ]);
                $user->stripe_custmer_id = $customerDetails->id;
                $user->save();
                $stripeCustomerId = $customerDetails->id;
                
            }
 
            if($inputData['card_save'] == 1){
            
                $cardDetails = \Stripe\Customer::allSources(
                    $stripeCustomerId
                );

                $tokenDetails = \Stripe\Token::retrieve(
                    $inputData['card_source_id']
                );

                $isAlreadyAdded = false;

                foreach($cardDetails as $card){
                    if($card['fingerprint'] == $tokenDetails['card']['fingerprint']){
                        $isAlreadyAdded = true;
                    }
                }

                if($isAlreadyAdded == false){
                    $source = \Stripe\Customer::createSource(
                        $stripeCustomerId,
                        ['source' => $inputData['card_source_id']]
                    );
            
                    $charge = \Stripe\Charge::create([
                        'amount' => $inputData['price'] * 100, 
                        'currency' => $inputData['currency'], 
                        'source' => $source->id,
                        'customer' => $stripeCustomerId 
                    ]);
                }
                else{
                    $charge = \Stripe\Charge::create([
                        'amount' => $inputData['price'] * 100, 
                        'currency' => $inputData['currency'], 
                        'source' => $inputData['card_source_id']
                    ]);
                }  

            }
            else
            {

                $charge = \Stripe\Charge::create([
                    'amount' => $inputData['price'] * 100, 
                    'currency' => $inputData['currency'], 
                    'customer' => $stripeCustomerId 
                ]);

            }
           
            $venderslot = venderSlot::find($inputData['vendor_slot_id']);
            $inputData['booking_start'] = $venderslot->start_time;
            $inputData['booking_end'] = $venderslot->end_time;
            $inputData['user_id'] = $user->id;
            $inputData['reference_id'] = mt_rand(1, 9999999);
            $booking = Booking::create($inputData);

            $inputData['booking_id'] = $booking->id;
            $bookingDetails = BookingDetail::create($inputData);
            $reportRecord = [];
            if(isset($inputData['report_ids'])){
                $reports = explode(',',$inputData['report_ids']);
                if(count($reports) > 0){
                    foreach($reports as $key => $report){
                        $reportRecord[$key]['booking_id'] = $booking->id;
                        $reportRecord[$key]['report_id'] = $report; 
                        $reportRecord[$key]['type'] = '1';
                    }
                    if($reportRecord){
                        BookingReports::insert($reportRecord);
                    }  
                }
            }
            
            if($charge){
                Transaction::create([
                    'user_id' => $user->id,
                    'trans_id' => $charge->id,
                    'payment_method' => $charge->payment_method_details->card->brand,
                    'vender_id' => $inputData['vender_id'],
                    'amount' => $inputData['price'],
                    'booking_id' => $booking->id,
                    'currency' => $inputData['currency'],
                    'original_amount' => $inputData['original_amount'],
                    'coupon_code' => $inputData['coupon_code'],
                    'status' => $charge->paid
                ]);
            }
            
            $result = Booking::with([
                                'bookingDetail', 
                                'bookingReports' => function($query){
                                    $query->with('reports');
                                }
                             ])
                             ->where('id',$booking->id)
                             ->first();

            $title = 'New Appointment has been added.';
            $message = 'New Appointment has been added by '. $user->firstname.'.';
            $dataPush = [
                'user_name' => $user->firstname,
                'booking_id' => $booking->id,
                'trans_id' => $charge->id,
                'vender_id' => $inputData['vender_id'],
                'status' => $charge->paid,
                'amount' => $inputData['price'],
            ];
            
            DeviceDetails::sendNotification($title ,$message,$inputData['vender_id'],$dataPush);

            Notification::create([
                'user_id' => $user->id, 
                'vender_id' => $inputData['vender_id'],
                'type' => 1,
                'title' => $title,
                'message' => json_encode($dataPush)
            ]);

            $this->response['status'] = 1;
            $this->response['message'] = trans('api/service.booked_successfully');
            $this->response['data'] = $result;
            /** Submit Transaction here if every thing working **/
            DB::commit();
            return response()->json($this->response, 200);
        
        }
        catch (\Exception $ex) {
            $this->response['message'] = trans('api/user.something_wrong');
            /*** Role back all queries for fresh entry ***/
            DB::rollBack();
            return response($this->response, 500);
        }
        
    }

    /**
     * @SWG\Get(
     *     path="/appointment",
     *     tags={"Booking"},
     *     summary="Get Appointment list",
     *     description="Get Appointment list using API's",
     *     operationId="getAppointment",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         required = true,
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="type",
     *         required = true,
     *         in="query",
     *         description="Upcoming,history and all(1,2 And 3)",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="page",
     *         required = true,
     *         in="query",
     *         description="pagination page number",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         required = true,
     *         in="query",
     *         description="number of records in list",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *            name="time-zone",
     *            in="header",
     *            description="Time Zone",
     *            type="string"
     *     ),  
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token And Unauthenticated"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function getAppointments(Request $request) {
        try{
            $user = Auth::User();
            $date = Carbon::now()->timezone($request->header('time-zone'))->format('Y-m-d H:i:s');
            $booking_data = Booking::select(['booking_date','vender_id','id','service_id','booking_start',DB::raw('CONCAT(booking_date, " ", booking_start) AS appointment_time'),'status'])
                                    ->with([
                                        'vendor' => function($query){
                                            $query->select(['firstname','id','image']);
                                        },
                                        'bookingDetail' => function($query){
                                            $query->select(['booking_id','patient_name']);
                                        },
                                        'service' => function($query){
                                            $query->select(['price','id','cat_id']);
                                        },
                                        'service.servicecategory' => function($query){
                                        $query->select(['cat_name','id']);
                                        },
                                        'review'
                                    ])
                                    ->where('user_id',$user->id);
            if($request->type != 3){
                $operatorDate = $request->type == 1 ? '>=' : '<=';
                $booking_data->having('appointment_time',$operatorDate,$date);
                                
            }

            $orderBy = $request->type == 1 ? 'ASC' : 'DESC';
            $booking_data = $booking_data->offset($request['limit']*$request['page'])
                                         ->orderBy('appointment_time',$orderBy)
                                         ->limit($request['limit'])
                                         ->get();


            if (!$booking_data) {
                $this->response['message'] = trans('api/service.no_booking_found');
                $this->response['data'] = $booking_data;
                $this->response['status'] = 0;
                return response()->json($this->response, 200);
            }

            $this->response['message'] = trans('api/service.booking_detail');
            $this->response['data'] = (object) $booking_data;
            $this->response['status'] =  1;
            return response()->json($this->response, 200);
            
        }
        catch (\Exception $ex) {
            
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/reports",
     *     tags={"Booking"},
     *     summary="Get User Reports list",
     *     description="Get User Reports list using API's",
     *     operationId="getReports",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         required = true,
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="booking_id",
     *         required = true,
     *         in="query",
     *         description="Booking id to fetch reports images",
     *         type="integer"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token And Unauthenticated"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function reportList(Request $request){
        try{
            $booking_data = BookingReports::where('booking_id',$request->booking_id)
                                        ->with(['reports'])
                                        ->get();
            
            if ($booking_data->isEmpty()) {
                $this->response['message'] = trans('api/service.no_booking_found');
                $this->response['data'] = $booking_data;
                $this->response['status'] = 0;
                return response()->json($this->response, 200);
            }
            $this->response['message'] = trans('api/service.booking_detail');
            $this->response['data'] = (object) $booking_data;
            $this->response['status'] = 1;
            return response()->json($this->response, 200);
        }
        catch (\Exception $ex) {
            
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Post(
     *     path="/addReview",
     *     tags={"Review"},
     *     summary="Create Review",
     *     description="Create Review For Docters",
     *     operationId="createReview",
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="Create Review object",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *              property="booking_id",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="rating",
     *              type="integer"
     *             ),
     *             @SWG\Property(
     *              property="feedback_message",
     *              type="string"
     *             ),
     *             @SWG\Property(
     *              property="is_like",
     *              type="integer"
     *             )
     *         )    
     *     ),
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
    public function addReview(AddReview $request) {
        try{
            $user = Auth::User();
            $data = array();
            $booking = Booking::find($request->booking_id);
            $data['user_id'] = $booking->user_id;
            $data['vender_id'] = $booking->vender_id;
            $data['booking_id'] = $booking->id;
            $data['rating'] = $request['rating'];
            $data['is_like'] = $request['is_like'] ? $request['is_like'] : '';

            if ($user->id == $booking->user_id) {
                $submitted_by = $booking->user_id;
                $submitted_to = $booking->vender_id;
            }

            $data['review_submitted_by'] = $submitted_by;
            $data['review_submitted_to'] = $submitted_to;
            $data['review_type'] = 'service';
            $data['feedback_message'] = $request['feedback_message'];

            if (Review::create($data)) {
                $this->response['message'] = trans('api/service.rated_successfully');
                $this->response['status'] = 1;
                return response()->json($this->response, 200);
            }
        }
        catch (\Exception $ex) {
            
            $this->response['message'] = $ex->getMessage();
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Patch(
     *     path="/appointment",
     *     tags={"Booking"},
     *     summary="Update Booking Status",
     *     description="Update Booking status details using API's",
     *     operationId="statusUpdate",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         required = true,
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *        name="body",
     *        in="body",
     *        description="Send update status record",
     *        @SWG\Schema(
     *           type="object",
     *           @SWG\Property(
     *              property="booking_id",
     *              type="integer"
     *            ),
     *            @SWG\Property(
     *              property="note",
     *              type="string"
     *            )
     *         )
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token And Unauthenticated"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function updateStatus(UpdateStatus $request){
        try{
            $booking_data = Booking::find($request['booking_id']);
            $booking_data->status = 2;
            $booking_data->save();
            $transaction = Transaction::where('booking_id',$booking_data->id)
                                        ->first();
                                                      
            BookingRefund::create([
                'booking_id' =>  $booking_data->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'note' => $request['note']
            ]);

            $this->response['message'] = trans('api/service.booking_cancel_successfully');
            $this->response['data'] = (object) $booking_data;
            $this->response['status'] = 1;
            return response()->json($this->response, 200);
        }
        catch (\Exception $ex) {
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/doctor_reports",
     *     tags={"Booking"},
     *     summary="Get Doctor list who created the reports",
     *     description="Get Doctor list who created the reports using API's",
     *     operationId="getDoctorReports",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         required = true,
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="page",
     *         required = true,
     *         in="query",
     *         description="pagination page number",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         required = true,
     *         in="query",
     *         description="number of records in list",
     *         type="integer"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token And Unauthenticated"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function doctorReports(Request $request){
        try{
                       
            $bookingReports = Booking::select(['user_id','vender_id','service_id','id'])
                                     ->whereHas('bookingDoctorReports')
                                     ->with([
                                            'bookingDoctorReports' => function($query){
                                                $query->select(['id','booking_id']);
                                            },
                                            'vendor' => function($query){
                                                $query->select(['image','firstname','id']);
                                            },
                                            'service.servicecategory' => function($query) {
                                                $query->select(['cat_name','id']);
                                            }
                                        ])
                                     ->where('user_id',Auth::user()->id)
                                     ->groupBy('vender_id')
                                     ->offset($request['limit']*$request['page'])
                                     ->limit($request['limit'])
                                     ->get();

            if ($bookingReports->isEmpty()) {
                $this->response['message'] = trans('api/service.no_booking_found');
                $this->response['data'] = $bookingReports;
                $this->response['status'] = 0;
                return response()->json($this->response, 200);
            }
            $this->response['message'] = trans('api/service.booking_detail');
            $this->response['data'] = (object) $bookingReports;
            $this->response['status'] = 1;
            return response()->json($this->response, 200);
        }
        catch (\Exception $ex) {
            
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/doctor_reports_list",
     *     tags={"Booking"},
     *     summary="Get Doctor Reports list",
     *     description="Get Doctor Reports list using API's",
     *     operationId="getDoctorReportsList",
     *     @SWG\Parameter(
     *         name="Authorization",
     *         required = true,
     *         in="header",
     *         description="Authorization Token",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="page",
     *         required = true,
     *         in="query",
     *         description="pagination page number",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         required = true,
     *         in="query",
     *         description="number of records in list",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="vendor_id",
     *         required = true,
     *         in="query",
     *         description="Vendor id",
     *         type="integer"
     *     ),
     *     @SWG\Response(response=200, description="Successful operation"),
     *     @SWG\Response(response=422, description="Validation Error and  Unprocessable Entity")*      ,
     *     @SWG\Response(response=401, description="Invalid Token And Unauthenticated"),
     *     @SWG\Response(response=500, description="Internal serve error")
     * )
     */
    public function doctorReportsList(DoctorReport $request){
        try{
                       
            $bookingReports = BookingDoctorReport::select(['id','data','booking_id','created_at'])
                                     ->whereHas('booking',function($query) use($request){
                                        $query->where('vender_id',$request['vendor_id']);
                                     })
                                     ->with([
                                         'booking' => function($query) {
                                            $query->select(['id','vender_id','service_id','reference_id']);
                                         },
                                         'booking.vendor' => function($query){
                                             $query->select(['firstname','id']);
                                         },
                                         'booking.bookingDetail' => function($query){
                                            $query->select(['patient_name','booking_id','age','gender']);
                                        },
                                        'booking.service' => function($query) {
                                            $query->select(['cat_id','id']);
                                        },
                                        'booking.service.servicecategory' => function($query) {
                                            $query->select(['cat_name','id']);
                                        }
                                     ])
                                     ->offset($request['limit']*$request['page'])
                                     ->limit($request['limit'])
                                     ->orderBy('created_at', 'DESC')
                                     ->get();

            if ($bookingReports->isEmpty()) {
                $this->response['message'] = trans('api/service.no_booking_found');
                $this->response['data'] = $bookingReports;
                $this->response['status'] = 0;
                return response()->json($this->response, 200);
            }
            $this->response['message'] = trans('api/service.booking_detail');
            $this->response['data'] = (object) $bookingReports;
            $this->response['status'] = 1;
            return response()->json($this->response, 200);
        }
        catch (\Exception $ex) {
            $this->response['message'] = trans('api/user.something_wrong');
            return response($this->response, 500);
        }  
    }
    

}
