<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Exception;

use App\Models\Lottery; // Adjust the namespace and path based on your actual model location

class LotteriesController extends Controller
{
    public function addLottery(Request $request, $lotteryId = null)
    {

        $uniVeriable = $lotteryId;

        //dd($request);
        
        try{
            
        
        if ($request->filled(['lot_name', 'mul_num' , 'is_open'])) {
            $user = auth()->user();


            // $weekdays = $request->input('weekdays');
            // //dd($request->input('weekdays'));
            // $decodedData = $weekdays;
             $weekdays = $request->input('weekdays'); // ["Cada dia", "Lunes"]

    // Check if $weekdays is an array
//     if (is_array($weekdays)) {
    
//         // Convert the array to a comma-separated string
//         $weekdaysString = implode(', ', $weekdays); // "Cada dia, Lunes"
//     }else{
//         $weekdaysString = str_replace(["[", "]", "'"], "", $weekdays); 
// // Result: "Cada dia, Lunes, Martes"

//         //$weekdaysString = json_decode(json_encode($request->input('weekdays'))); // ["Cada dia", "Lunes"]
        
//     }
            





            $lotData = [
                'lot_name'      => $request->input('lot_name'),
                'multiply_number' => $request->input('mul_num'),
                'winning_type'  => $request->input('winning_type'),
                'user_added_id' =>  $user->user_id,
                'lot_opentime'  => $request->input('fromtime'),
                'lot_closetime' => $request->input('totime'),
                // 'lot_colorcode' => $request->input('colorcode'),
                'lot_weekday'   => json_encode($weekdays),
                'is_open'   => $request->input('is_open'),
            ];
            
            // dd($request);
            
            if ($request->filled('colorcode')) {
                $lotData['lot_colorcode'] = $request->input('colorcode');
            }

            // Handle image upload
            if ($request->hasFile('image')) {


                $imgName = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
                $request->file('image')->storeAs('public/images', $imgName);
                $imgUrlForApi = Storage::url('images/' . $imgName);

                $lotData['img_url'] = $imgUrlForApi;
            }

            // Check if editing an existing lottery or adding a new one
            if ($lotteryId !== null) {
                // Editing an existing lottery

                $lotData['user_edited_id'] = $user->user_id;
                //dd($lotData);
                DB::table('lotteries')->where('lot_id', $lotteryId)->update($lotData);

                 $lotteryDetails = DB::table('lotteries')->where('lot_id', $lotteryId)->first();
            } else {
                // Adding a new lottery
                DB::table('lotteries')->insert($lotData);
                $lotteryId = DB::getPdo()->lastInsertId();
                $lotteryDetails = DB::table('lotteries')->where('lot_id', $lotteryId)->first();
            }

            $response = [
                'data' => [
                    'lottery_details' => $lotteryDetails,
                ],
                'success' => true,
                'msg'       => (empty($uniVeriable)) ? 'Lottery Added Successfully' : 'Lottery Updated Successfully',
            ];
        } else {

            $response = [
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ];
        }

        return response()->json($response);
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }


    public function deleteLottery(Lottery $lotteryId)
{

   // dd($lottery);

    try {
        DB::table('lotteries')->where('lot_id', $lotteryId)->delete();

        $response = [
            'success' => true,
            'msg'       => 'Lottery deleted successfully',
        ];
    } catch (\Exception $e) {
        $response = [
            'success' => false,
            'msg'       => 'Error deleting lottery: ' . $e->getMessage(),
        ];
    }

    return response()->json($response);
}



//lotteries list all
public function getLotteriesListAll($lotteryId = null)
{

    $baseUrl = url('/'); // Assuming you want to use the base URL of your Laravel application
    $userRole = auth()->user()->user_role;
    $userId = auth()->user()->user_id;
    //dd($userId);

    $adminIdThis = $this->getAdminId($userId);

    //dd($adminIdThis);
    $adminIdThis = $this->getAdminId($userId);

    $query = DB::table('lotteries')
        ->select('lot_id', 'lot_name AS name', 'is_open', 'multiply_number', DB::raw('IFNULL(img_url, "/assets/images/logo2.png") AS img_url'), 'winning_type', 'lot_opentime', 'lot_closetime','lot_colorcode')
        ->when($userRole != 'superadmin', function ($query) use ($adminIdThis) {
            return $query->where('user_added_id', $adminIdThis);
        })
        ->get();

    foreach ($query as $lottery) {
    if ($lottery->img_url != '/assets/images/logo2.png') {
        $imageName = basename($lottery->img_url); // Extract the image name
        if (!Storage::exists('public/images/' . $imageName)) {
            $lottery->img_url = '/assets/images/logo2.png';
        }
    }
}


        $lotteries = $query->map(function ($lottery) use ($baseUrl) {
    $days = DB::table('lotteries')->where('lot_id', $lottery->lot_id)->value('lot_weekday');

    // Try decoding as JSON first
    $decodedDays = json_decode($days, true);

    $lottery->lot_weekday = $decodedDays != null && !empty($decodedDays) ? $decodedDays : ['Cada dia'];
    if (!is_array($decodedDays)) {
    $lottery->lot_weekday = [(string) $decodedDays];
    }

    // Concatenate base URL with img_url
    $lottery->img_url = $baseUrl . $lottery->img_url;

    return $lottery;
});



    if ($lotteryId) {
        $lotId = request('lot_id');
        $lot = DB::table('lotteries')->where('lot_id', $lotId)->first();
        return response()->json($lot);
    }
    
    
    $lotteries = collect($lotteries)->map(function ($item) {
    // Convert object to array if necessary
    $item = (array) $item;

    // Map over the item and ensure that values that are arrays or objects are not converted
    return collect($item)->map(function ($value, $key) {
        // Only convert scalar values (not arrays or objects) to string
        return is_scalar($value) ? (string) $value : $value;
    })->toArray();
});


    return response()->json([

    'success' => true,
    'msg' => 'Lottery List',
    'data' => $lotteries,
]);
}



public function getLotteriesListAllWithTime()
{
    $user = auth()->user();
    if (!$user) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user_id',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    $userId = $user->user_id;
    $userRole = $user->user_role;

    if ($userRole === 'admin') {
        $thisAdminId = $userId;
    } elseif ($userRole === 'manager') {
        $manager = User::find($user->added_user_id);
        if ($manager && $manager->user_role === 'manager') {
            $admin = User::find($manager->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            $thisAdminId = null;
        }
    } elseif ($userRole === 'seller') {
        $addedByUser = User::find($user->added_user_id);
        if ($addedByUser && $addedByUser->user_role === 'admin') {
            $thisAdminId = $addedByUser->user_id;
        } elseif ($addedByUser && $addedByUser->user_role === 'manager') {
            $admin = User::find($addedByUser->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            $thisAdminId = null;
        }
    } else {
        $thisAdminId = null;
    }

    if (!$thisAdminId) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user role or hierarchy',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    date_default_timezone_set("America/Guatemala");
    $serverTimeWithGuatemala = now()->format('H:i:s');
    $currentDay = now()->format('l'); // Current day in English (e.g., "Monday")

    $daysArr = [
        'everyday' => 'Cada dia',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sabado',
        'Sunday' => 'Domingo',
    ];

    $dayInSpanish = $daysArr[$currentDay];

    // Get lotteries with winning_type 7
    $lotteriesType7 = DB::table('lotteries')
        ->select(
            'lot_id',
            'lot_name AS name',
            'is_open',
            'multiply_number',
            DB::raw('IFNULL(img_url, "/assets/images/logo2.png") AS img_url'),
            'winning_type',
            'lot_opentime',
            'lot_closetime',
            'user_added_id',
            DB::raw("
                CASE 
                    WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)'
                    WHEN lot_colorcode IS NULL THEN 'Color(0xffEAF8A3)'
                    ELSE lot_colorcode
                END AS colorcode
            ")
        )
        ->where('winning_type', 7)
        ->where('user_added_id', $thisAdminId)
        ->where('is_open', 1)
        ->where(function ($query) use ($dayInSpanish, $serverTimeWithGuatemala) {
            $query->whereRaw("JSON_CONTAINS(lot_weekday, ?) = 0", ['"' . $dayInSpanish . '"'])
            ->orWhere(function ($query) use ($dayInSpanish, $serverTimeWithGuatemala) {
                $query->where(function ($query) use ($serverTimeWithGuatemala) {
            $query->where('lot_opentime', '>', $serverTimeWithGuatemala)
                ->orWhere('lot_closetime', '<', $serverTimeWithGuatemala);
                });
      });

        })
        ->get();

    // Get lotteries with winning_type 1
    $lotteriesType1 = DB::table('lotteries')
        ->select(
            'lot_id',
            'lot_name AS name',
            'is_open',
            'multiply_number',
            DB::raw('IFNULL(img_url, "/assets/images/logo2.png") AS img_url'),
            'winning_type',
            'lot_opentime',
            'lot_closetime',
            'user_added_id',
            DB::raw("CASE WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)' WHEN lot_colorcode IS NULL THEN 'Color(0xffEAF8A3)' ELSE lot_colorcode END AS colorcode")
        )
        ->where('winning_type', 1)
        ->where('user_added_id', $thisAdminId)
        ->where('is_open', 1)
        ->where(function ($query) use ($dayInSpanish, $serverTimeWithGuatemala) {
            $query->where(function ($subQuery) use ($dayInSpanish) {
                $subQuery->whereRaw("JSON_CONTAINS(lot_weekday, '\"Cada dia\"')") // Matches "everyday"
                         ->orWhereRaw("JSON_CONTAINS(lot_weekday, ?)", ['"' . $dayInSpanish . '"']); // Matches current day
            })
            ->where('lot_opentime', '<', $serverTimeWithGuatemala)
            ->where('lot_closetime', '>', $serverTimeWithGuatemala);
        })
        ->get();

    // Combine the results
    $lotteries = $lotteriesType1->merge($lotteriesType7);

    if ($lotteries->isNotEmpty()) {
        $lotteries->transform(function ($lottery) {
            $lottery->timenow = now()->format('H:i:s');
            $lottery->img_url = url('/') . $lottery->img_url;
            return $lottery;
        });

        $lotteries = collect($lotteries)->map(function ($item) {
            return collect($item)->map(function ($value, $key) {
                return (string) $value; // Convert value to string
            })->toArray();
        });

        return response()->json([
            'timenow' => now()->format('H:i:s'),
            'success' => true,
            'msg' => 'Lotteries get',
            'data' => $lotteries,
        ]);
    } else {
        return response()->json([
            'timenow' => now()->format('H:i:s'),
            'success' => true,
            'data' => [],
            'msg' => 'Lotteries not opened yet',
        ]);
    }
}


public function getAdminId($userId)
{
    $user = User::find($userId);

    if (!$user) {
        return null; // User not found
    }

    if ($user->user_role === 'admin') {
        return $user->user_id; // Return the user ID if the user is an admin
    } elseif ($user->user_role === 'manager' || $user->user_role === 'seller') {
        $addedByUser = User::find($user->added_user_id);

        if ($addedByUser) {
            if ($addedByUser->user_role === 'admin') {
                return $addedByUser->user_id; // Return the admin ID if the added user is an admin
            } elseif ($addedByUser->user_role === 'manager') {
                $admin = User::find($addedByUser->added_user_id);
                if ($admin && $admin->user_role === 'admin') {
                    return $admin->user_id; // Return the admin ID if the added user is a manager and their superior is an admin
                }
            }
        }
    }

    return null; // Return null if the admin ID is not found
}


}
