<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LotteriesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RiddlesController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SaleReportController;
use App\Http\Controllers\WinnigController;


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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/addLoan', [UserController::class, 'addLoan']);
    Route::post('/dashboard', [DashboardController::class, 'dashboard']);
    //for view details
    Route::post('/getdashboard/{user_id}', [DashboardController::class, 'getdashboard']);
    //collect balance cut
    Route::post('/collectamount', [DashboardController::class, 'collectBalance']);
    //lotteries
    //Route::post('/lotteries', [LotteriesController::class, 'addLottery']);
    Route::post('/lotteries/{lottery?}', [LotteriesController::class, 'addLottery']);
    Route::delete('/lotteries/{lottery}', [LotteriesController::class, 'deleteLottery']);
    // check if open orr not
    Route::get('/lotteryListWithTime/{lot_id?}', [LotteriesController::class, 'getLotteriesListAllWithTime']);
    // all lottery list
    Route::get('/lotteryList/{lot_id?}', [LotteriesController::class, 'getLotteriesListAll']);
    //add seller or other users
    Route::post('/addUsers/{user_id?}', [UserController::class, 'addusers']);
    //edit user only status or commission
    Route::post('/edituser/{user_id}', [UserController::class, 'edituser']);


    Route::get('/requestuserlist' , [UserController::class, 'requestUserList']);
    //user list based on role
    Route::get('/userList' , [UserController::class, 'userList']);
    //add Riddles
    Route::post('/changePassword' , [UserController::class, 'changePassword']);
    Route::post('/uploadProfileImage', [UserController::class, 'uploadProfileImage']);
    Route::post('/addRiddles/{rid_id?}' , [RiddlesController::class, 'store']);

    Route::get('/deleteriddle/{rid_id?}' , [RiddlesController::class, 'destroy']);


    //Sale related controllers
    //limit routes
    Route::post('/addLimit' , [SaleController::class, 'addLimit']);
    Route::get('/limitlist/{user_id}' , [SaleController::class, 'limitlist']);
    Route::delete('/deleteLimitsingle/{limit_id}' , [SaleController::class, 'deleteLimitsingle']);
    Route::delete('/deleteLimitlottery' , [SaleController::class, 'deleteLimitlottery']);

    //Limit routes end
    Route::post('/checkLimit' , [SaleController::class, 'checkLimit']);
    //orders ticket
    Route::post('/createOrder' , [OrderController::class, 'createOrder']);
    Route::get('/orderList' , [OrderController::class, 'orderList']);

    Route::get('/orderprint/{id}' , [OrderController::class, 'orderprint']);
    
    Route::get('/printOrder/{id}' , [OrderController::class, 'printOrder']);
    
    Route::get('/deleteorder/{id}' , [OrderController::class, 'deleteorder']);
    
    Route::get('/getNotifications', [UserController::class, 'getNotifications']);



    Route::post('/saleReport' , [SaleReportController::class, 'saleReport']);


    //winning number add
    Route::post('/winadd' , [WinnigController::class , 'addWinningNumber']);
    Route::get('/winningcustomer' , [WinnigController::class , 'winListAll']);
    //seller wining orders
    Route::get('/add_winnigamountbyseller' , [DashboardController::class , 'addWinningamountbySeller']);
    Route::get('/winnigorderslist' , [WinnigController::class , 'getWinningOrders']);
    //winning amout paid by seller

    Route::get('/getUserCredit', [UserController::class, 'getUserCredit']);
    
    //Riddles list
    Route::get('/riddleList' , [RiddlesController::class, 'index']);

});

Route::get('/printOrder/{id}', [OrderController::class, 'printOrder'])->name('printOrder');

Route::post('/resetPassword', [UserController::class, 'resetPassword']);
Route::post('/forgotPassword', [UserController::class, 'forgotPassword']);

Route::post('/login', [LoginController::class, 'login']);
//Route::post('/admin', [DashboardController::class, 'admin']);
Route::post('/requestAccess' , [UserController::class, 'requestUser']);


//winning mamagement

Route::get('/winingList' , [RiddlesController::class, 'winingList']);
