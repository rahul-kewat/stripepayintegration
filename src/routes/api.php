

<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

      
        
Route::group(['namespace' => 'Devrahul\Stripepayintegration\Http\Controllers'],function(){
        Route::get('stripe', 'API\v1\StripePaymentController@stripe');
        Route::post('stripe', 'API\v1\StripePaymentController@stripePost')->name('stripe.post');
});
