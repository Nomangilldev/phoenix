<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
class SaleController extends Controller
{


    public function limitlist(Request $request, $user){

        //dd($user);
        $response = [];

        try {
            $queryResult = DB::table('limit_game')
                ->join('lotteries', 'lotteries.lot_id', '=', 'limit_game.lottery_id')
                ->where('limit_game.user_id', $user)
                ->get();
        } catch (\Exception $e) {
            dd($e->getMessage());
        }


    //dd($queryResult);

foreach ($queryResult as $r) {
    $lotteryName = $r->lot_name;
    $lotterycolor = $r->lot_colorcode;
    $lotteryId = $r->lottery_id;
    $limitData = [
        'limit_ball' => $r->limit_ball,
        'limit_frac' => $r->limit_frac,
        'limit_id' => (string) $r->limit_id,
        // Add other limit-related columns here
    ];

    if (!isset($response[$lotteryName])) {
        $response[$lotteryName] = [
            'lottery_color' => $lotterycolor,
            'lot_id' => $lotteryId,
            'limits' => [],
        ];
    }

    $response[$lotteryName]['limits'][] = $limitData;
}

$finalResponse = [];
foreach ($response as $lotteryName => $lotteryInfo) {
    $lotteryInfo['lottery_name'] = $lotteryName;
    $finalResponse[] = $lotteryInfo;
}

if (empty($finalResponse)) {
    $re = [
        'data' => [],
        'success' => false ,
        'msg' => 'Nothing Found'
    ];
} else {
    $re = [
        'data' => $finalResponse,
        'success' => true,
        'msg' => 'Get Data'
    ];
}

//echo json_encode($re);


return response()->json($re);

    }


    public function checkLimit(Request $request)
{
    $entityBody = $request->getContent();
    $user = auth()->user();
    
    $today = now()->toDateString();
       
     

    $obj = json_decode($entityBody);
    $size = count(@$obj->cartDataList);
    date_default_timezone_set("America/Guatemala");
    $servertimewithgutemala = now()->format('H:i:s');

    $testArr = [];
    $fracSum = 0;
    $quatSum = 0;
    for ($i = 0; $i < $size; $i++) {
        $lotteryid = ($obj->cartDataList[$i]->loteryId);
        $frac = (int)($obj->cartDataList[$i]->frac);
        $fracSum += $frac;
        $number = ($obj->cartDataList[$i]->number);
        $quator = ($obj->cartDataList[$i]->quator);
        $quatSum += $quator;
        $loteryName = ($obj->cartDataList[$i]->loteryName);
        $colorcode = ($obj->cartDataList[$i]->lotColor);
        //dd($user->user_id);
        $currentDateTime = now()->format('H:i:s');
//               $d = DB::select("SELECT lg.*, l.*
//             FROM lotteries l
//             LEFT JOIN limit_game lg ON l.lot_id = lg.lottery_id AND lg.status = '1'
//             WHERE l.lot_id = '$lotteryid'
//                 AND '$currentDateTime' >= l.lot_opentime
//                 AND '$currentDateTime' <= l.lot_closetime
//                 AND lg.limit_ball = '$number'
//                 AND lg.limit_frac <= '$frac'
//                 AND (lg.user_id = '$user->user_id' OR lg.user_id = '$added_user_id->user_id')
// ");
        
       $d = DB::select("SELECT * FROM limit_game WHERE 
    lottery_id = '$lotteryid'
    AND limit_ball = '$number'
    AND limit_frac >= '$frac'
    AND (user_id = '$user->user_id' OR user_id = '$user->added_user_id')
");

    
    $orderTotalSum = Order::where('user_id', $user->user_id)
    ->where('order_date', $today)
    ->sum('grand_total');
    
    $orderTotalFrac = $orderTotalSum * 20;

    $totalLimitCredit = $orderTotalSum + $quatSum;
    $totalLimitCreditFrac = $orderTotalFrac + $fracSum;
    if ($totalLimitCredit > $user->user_amount || $totalLimitCreditFrac > $user->user_frac) {
        return response()->json(['success' => false, 'msg' => 'The amount exceeds your credit limit']);
    }

        

        
        // $lotery = DB::select("SELECT lot_id,lot_name AS name,is_open,multiply_number,img_url,winning_type,lot_opentime,lot_closetime,
        //     CASE
        //         WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)'
        //         WHEN lot_colorcode IS NULL THEN  'Color(0xffEAF8A3)'
        //         ELSE lot_colorcode
        //     END
        //     AS colorcode FROM lotteries WHERE lot_id = '$lotteryid'
        // ");

        // $colorcode = collect($lotery)->first();

        $d1 = collect($d)->first();
        

        if ($d1) {
            //dd($d1);
            $sts = "false";
        } else {
            $sts = "true";
        }
        //dd($sts);

        $testArr[] = [
            'number' => $number,
            'quator' => $quator,
            'frac'   => "$frac",
            'loteryId' => $lotteryid,
            'limit'   => $sts,
            'loteryName' => $loteryName,
            'lotColor' => $colorcode,
        ];
    }

    $finalArr = [
        'success' => true,
        'msg' => 'Lottery List',
        'cartDataList' => $testArr,

    ];

    return response()->json($finalArr);
}




    public function addLimit(Request $request)
    {
        $user = $request->input('user_id');
        $frac = $request->input('limit_frac');
        $limit_ball = $request->input('limit_ball');

        $integerIDs = $limit_ball;

        $added_user_id = auth()->user()->user_id;

        $lotid = $request->input('lottery_id');


            try {
                DB::beginTransaction();

                foreach ($integerIDs as $value) {
                    $value = sprintf("%02d", $value);
                    
                    $existingLimit = DB::table('limit_game')->where('user_id', $user)->where('lottery_id', $lotid)->where('limit_ball', $value)->first();
                    
                    if($existingLimit){
                        DB::table('limit_game')
                    ->where('limit_id', $existingLimit->limit_id)
                    ->update([
                        'lottery_id' => $lotid,
                        'limit_frac' => $frac,
                        'user_id' => $user,
                        'limit_ball' => $value,
                        'added_user_id' => $added_user_id,
                    ]);
                    }else{
                    DB::table('limit_game')->insert([
                        'lottery_id' => $lotid,
                        'limit_frac' => $frac,
                        'user_id' => $user,
                        'limit_ball' => $value,
                        'added_user_id' => $added_user_id,
                    ]);
                    }
                    
                }

                DB::commit();

                $response = [
                    'success' => true,
                    'msg' => 'Limit Added successfully for numbers ' . count($integerIDs),
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                $response = [

                    'success' => false,
                     'msg'    => $e->getMessage(),
                ];
            }


        return response()->json($response);
    }



public function deleteLimitsingle(Request $request, $limitID)
{
    try {
        // Start a database transaction
        DB::beginTransaction();

        // Delete the limit using the DB facade
        DB::table('limit_game')->where('limit_id', $limitID)->delete();

        // Commit the transaction if the deletion is successful
        DB::commit();

        return response()->json([
            'success' => true,
            'msg' => 'Limit Deleted successfully for numbers ',
        ], 200);
    } catch (\Exception $e) {
        // Rollback the transaction if an exception occurs
        DB::rollback();

        // Handle exceptions (e.g., database error)
        return response()->json([
            'success' => false,
            'msg' => 'Failed to delete limit. ' . $e->getMessage()

        ], 500);
    }
}


public function deleteLimitlottery(Request $request)
{
    try {
        // Start a database transaction
        DB::beginTransaction();
        $lotteryID = $request->input('lottery_id');
        $userID = $request->input('user_id');
        // Delete the limit using the DB facade
        DB::table('limit_game')
        ->where('lottery_id', $lotteryID)
        ->where('user_id', $userID)
        ->delete();

        // Commit the transaction if the deletion is successful
        DB::commit();

        return response()->json([
            'success' => true,
            'msg' => 'Limit Deleted successfully ',
        ], 200);
    } catch (\Exception $e) {
        // Rollback the transaction if an exception occurs
        DB::rollback();

        // Handle exceptions (e.g., database error)
        return response()->json([
            'success' => false,
            'msg' => 'Failed to delete limit. ' . $e->getMessage()

        ], 500);
    }
}




}
