public function getLotteriesListAllWithTime()
{

    $user = auth()->user();

    $userRole = auth()->user()->user_role;
    $userId = auth()->user()->user_id;

    if (!$user) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user_id',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    // if ($user->user_role === 'admin') {
    //     $thisAdminId = $userId;
    // } else {
    //     $manager = User::find($user->added_user_id);
    //     if ($manager && $manager->user_role === 'manager') {
    //         $admin = User::find($manager->added_user_id);
    //         $thisAdminId = $admin ? $admin->user_id : null;
    //     } else {
    //         $thisAdminId = null;
    //     }
    // }

    if ($user->user_role === 'admin') {
        $thisAdminId = $userId;
    } elseif ($user->user_role === 'manager') {
        $manager = User::find($user->added_user_id);

        if ($manager && $manager->user_role === 'manager') {
            // If the user is a manager, find the admin of the manager
            $admin = User::find($manager->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            // If the user is a manager but their superior is not a manager, set to null
            $thisAdminId = null;
        }
    } elseif ($user->user_role === 'seller') {
        // If the user is a seller, check if added by admin or manager

        $addedByUser = User::find($user->added_user_id);
        //dd($addedByUser);
        if ($addedByUser && $addedByUser->user_role === 'admin') {
            // If added by admin, set the admin ID
            //dd($addedByUser);
            $thisAdminId = $addedByUser->user_id;

        } elseif ($addedByUser && $addedByUser->user_role === 'manager') {
            // If added by manager, find the admin of the manager
            $admin = User::find($addedByUser->added_user_id);
            $thisAdminId = $admin ? $admin->user_id : null;
        } else {
            // If the added user is not found or is not admin/manager, set to null
            $thisAdminId = null;
        }
    } else {
        // For any other role, set to null
        $thisAdminId = null;
    }




   // dd($thisAdminId);
    if (!$thisAdminId) {
        return response()->json([
            'success' => false,
            'msg' => 'Invalid user role or hierarchy',
            'timenow' => now()->format('H:i:s')
        ]);
    }

    date_default_timezone_set("America/Guatemala");
    $serverTimeWithGuatemala = now()->format('H:i:s');

    $daysArr = [
        'everyday' => 'Cada dia',
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Juevez',
        'Friday' => 'Viernes',
        'Saturday' => 'Sabado',
        'Sunday' => 'Domingo',
    ];

    // Before the query execution

    $query = DB::table('lotteries')
    ->select(
        'lot_id',
        'lot_name AS name',
        'is_open',
        'multiply_number',
        'img_url',
        'winning_type',
        'lot_opentime',
        'lot_closetime',
        'user_added_id',
        'lot_colorcode',
        DB::raw("
            CASE
                WHEN lot_colorcode = '' THEN 'Color(0xff1cff19)'
                WHEN lot_colorcode IS NULL THEN 'Color(0xffEAF8A3)'
                ELSE lot_colorcode
            END AS colorcode
        ")
    )
    ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        $query->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
            $query->where('lot_weekday', $daysArr[now()->format('l')])
                ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
                    $query->where('lot_closetime', '>', $serverTimeWithGuatemala)
                        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
                            $query->where('lot_opentime', '>', $serverTimeWithGuatemala)
                                ->where('lot_closetime', '<', now()->subHours(3)->format('H:i:s'));
                        });
                })
                ->orWhere('lot_weekday', '!=', $daysArr[now()->format('l')]);
        })
        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
            $query->where('winning_type', 1)
                ->where('lot_opentime', '>', $serverTimeWithGuatemala)
                ->where('lot_closetime', '<', $serverTimeWithGuatemala);
        })
        // ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //     $query->where('winning_type', 7)
        //         ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //             $query->where('lot_weekday', $daysArr[now()->format('l')])
        //                 ->where(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //                     $query->where('lot_closetime', '>', $serverTimeWithGuatemala)
        //                         ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
        //                             $query->where('lot_closetime', '<', $serverTimeWithGuatemala)
        //                                 ->where('lot_closetime', '>', now()->subHours(3)->format('H:i:s'));
        //                         });
        //                 })
        //                 ->orWhere('lot_weekday', '!=', $daysArr[now()->format('l')]);
        //         });
        // })
        //demo start
        ->orWhere(function ($query) use ($daysArr, $serverTimeWithGuatemala) {
    $query->where('winning_type', 7)
        ->orWhere('lot_weekday', '!=', now()->format('l'));
})
     //demo end
        ->orWhere(function ($query) use ($serverTimeWithGuatemala) {
            $query->where('winning_type', 1)
                ->where('lot_opentime', '<', $serverTimeWithGuatemala)
                ->where('lot_closetime', '>', $serverTimeWithGuatemala);
        });
    })
    ->where('user_added_id', $thisAdminId)
    ->get();









    if ($query->isNotEmpty()) {

        $lotteries = $query->map(function ($lottery) {
            $days = DB::table('lotteries')->where('lot_id', $lottery->lot_id)->value('lot_weekday');
            $lottery->lot_weekday = $days ? explode(',', $days) : ['Cada dia'];

             // Concatenate base URL with img_url
             $baseUrl = url('/');
    $lottery->img_url = $baseUrl . $lottery->img_url;
            return $lottery;
        });
        //dd($lotteries);
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
            'msg' => 'Lotteries not opened yet',

        ]);
    }
}