

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['namespace' => 'Devrahul\Stripepayintegration\Http\Controllers'],function(){
   
    Route::get('stripe', 'API\v1\StripePaymentController@stripe');
    Route::post('stripe', 'API\v1\StripePaymentController@stripePost')->name('stripe.post');

Route::group(['middleware' => 'auth'], function() {
    Route::get('/home', 'API\v1\HomeController@index')->name('home');
    Route::get('/plans', 'API\v1\PlanController@index')->name('plans.index');
    Route::get('/plan/{plan}', 'API\v1\PlanController@show')->name('plans.show');
});

Route::group(['middleware' => 'auth'], function() {
    Route::get('/home', 'HomeController@index')->name('home');
    Route::get('/plans', 'PlanController@index')->name('plans.index');
    Route::get('/plan/{plan}', 'PlanController@show')->name('plans.show');
    Route::post('/subscription', 'SubscriptionController@create')->name('subscription.create');
});
});

