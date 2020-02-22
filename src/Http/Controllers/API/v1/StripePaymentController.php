<?php
namespace Devrahul\Stripepayintegration\Http\Controllers\API\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Devrahul\Stripepayintegration\Http\Requests\StripeRequest;
use Session;
use Stripe;
use App\Helpers\Response;
use DB;


class StripePaymentController extends Controller

{
    protected $response = [
        'status' => 0,
        'message' => '',
    ];

    public function stripe()
    {
        return view('stripe');
    }

    public function stripePost(StripeRequest $request)
    {
        try{
            // Getting all the request
            $inputData = $request->all();
            

            DB::beginTransaction();
            // $user = Auth::user();
            // Getting Stripe Customer ID of the Customer
                // $stripeCustomerId = $user->stripe_custmer_id;

            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $stripeCustomerId ="cus_GmWdXjf7bdm9oF";
            $cardDetails = \Stripe\Customer::allSources(
                $stripeCustomerId
            );

            
            Stripe\Charge::create ([
                    "amount" => $request['amount'] * 100,
                    "currency" => "usd",
                    // "source" => $request->stripeToken,
                    "customer" => $request['customer_name'],
                    "customer" => $stripeCustomerId,
                    "description" => "Test payment" 
            ]);
    
            

            
            DB::commit();

            $this->response['message'] = "Payment Successfully Accepted";
            $this->response['data'] = $result;
            return response($this->response, 200);
        }
        catch(\Stripe\Exception\CardException $e) {
            DB::rollback();
            // Since it's a decline, \Stripe\Exception\CardException will be caught
            $this->response['message'] = 'Message is:' . $e->getError()->message . '\n' . 'Code is:' . $e->getError()->code . '\n';
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);

        }
        catch (\Stripe\Exception\RateLimitException $e) {
            DB::rollback();
            // Too many requests made to the API too quickly
            $this->response['message'] = "Too many requests made to the API too quickly";
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        catch (\Stripe\Exception\InvalidRequestException $e) {
            DB::rollback();
            $this->response['message']= "Invalid parameters were supplied to Stripe's API";
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
            // Invalid parameters were supplied to Stripe's API
        }
        catch (\Stripe\Exception\AuthenticationException $e) {
            DB::rollback();
            $this->response['message']="Authentication with Stripe's API failed" . '\n' . "Or Maybe you changed API keys recently";
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
        }
        catch (\Stripe\Exception\ApiConnectionException $e) {
            DB::rollback();
            $this->response['message']="Network communication with Stripe failed";
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
            // Network communication with Stripe failed
        }
        catch (\Stripe\Exception\ApiErrorException $e) {
            DB::rollback();
            $this->response['message']="API Error Ocurred";
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
            
            // Display a very generic error to the user, and maybe send
            // yourself an email
        }
        catch (\Stripe\Error\Base $e) {
            DB::rollback();
            // Code to do something with the $e exception object when an error occurs
            $this->response['message'] = $e->getMessage();
            return response($this->response, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        catch(Exception $ex){
            DB::rollback();
            $this->response['message'] = "Something Went Wrong!! Please try after sometime";
            return response($this->response, 500);
        }
    }

}