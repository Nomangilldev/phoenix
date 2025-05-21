<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Illuminate\Http\Request;

class WinnigController extends Controller
{
    public function addWinningNumber(Request $request)
    {
        //dd(auth()->user());

        if (!empty($request->lot_id) && !empty($request->win_number)) {
            $dateInput = $request->input('date');
            $date = \Carbon\Carbon::createFromFormat('d M, Y', $dateInput)->format('Y-m-d');
            $lot  = $request->lot_id;
            $win  = $request->win_number;
            $user = auth()->user()->user_id;

            // 🛑 Duplication check here
            $exists = DB::table('winning_numbers')
                ->where('add_date', $date)
                ->where('lot_id', $lot)
                ->where('number_win', $win)
                ->exists();

            if ($exists) {
                return response()->json([
                    'msg' => 'This winning number already exists for the selected date.',
                    'success' => false,
                ]);
            }

            DB::beginTransaction();

            try {
                $inserted = DB::table('winning_numbers')->insert([
                    'add_date' => $date,
                    'lot_id' => $lot,
                    'number_win' => $win,
                    'added_by' => $user
                ]);


                if ($inserted) {
                    $totalwinadded = 0;
                    $getwinorder = DB::table('order_item')
                        ->select('order_item_id', 'order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount', 'lottery_gone', 'transaction_paid_id', 'order_item_status', 'winning_amount', 'lotterycollected', DB::raw('cast(adddatetime as date)'))
                        ->where('lottery_gone', '0')
                        ->where('product_id', $lot)
                        ->where('lot_number', $win)
                        ->where(DB::raw('cast(adddatetime as date)'), $date)
                        ->get();

                    // dd($getwinorder);

                    $lotDetails = DB::table('lotteries')->where('lot_id', $lot)->first();

                    foreach ($getwinorder as $rowq) {
                        $winAmount = $rowq->lot_amount * $lotDetails->multiply_number;
                        $totalwinadded += $winAmount;

                        DB::table('order_item')
                            ->where('order_item_id', $rowq->order_item_id)
                            ->update(['winning_amount' => $winAmount]);

                        $GetOrder = DB::table('order_item')->where('order_item_id', $rowq->order_item_id)->value('order_id');
                        $GetOrderitemid = DB::table('order_item')->where('order_item_id', $rowq->order_item_id)->value('order_item_id');
                        $user_idNow = DB::table('orders')->where('order_id', $GetOrder)->value('user_id');
                    }

                    $getgoneorder = DB::table('order_item')
                        ->where('lottery_gone', '0')
                        ->where('product_id', $lot)
                        ->where(DB::raw('cast(adddatetime as date)'), $date)
                        ->get();

                    foreach ($getgoneorder as $goneupdate) {
                        DB::table('order_item')
                            ->where('order_item_id', $goneupdate->order_item_id)
                            ->update(['lottery_gone' => '1']);
                    }

                    // Add your transaction insertion here

                    DB::commit();

                    $arr = [
                        'msg' => 'Winning Number Added..!',
                        'success' => true,
                    ];
                } else {
                    $arr = [
                        'msg' => 'Failed to add winning number.',
                        'success' => false,
                    ];
                }
            } catch (\Exception $e) {
                DB::rollback();
                $arr = [
                    'msg' => $e->getMessage(),
                    'success' => false,
                ];
            }

            return response()->json($arr);
        } else {
            return response()->json([
                'msg' => 'Missing required parameters.',
                'success' => false,
            ]);
        }
    }


    public function winListAll(Request $request)
    {

        $user = auth()->user()->user_id;

        $getuserde = DB::table('users')->where('user_id', $user)->first();

        if ($getuserde->user_role == 'admin') {
            $thisadmin = $getuserde->user_id;
        } else {
            $getuser1 = DB::table('users')->where('user_id', $getuserde->added_user_id)->first();

            if ($getuser1->user_role == 'admin') {
                $thisadmin = $getuser1->user_id;
            } else {
                $getuser3 = DB::table('users')->where('user_id', $getuserde->added_user_id)->first();
                $getuser31 = DB::table('users')->where('user_id', $getuser3->added_user_id)->first();
                $thisadmin = $getuser31->user_id;
            }
        }

        $winningList = DB::table('winning_numbers')
            ->select(
                DB::raw("DATE_FORMAT(winning_numbers.add_date, '%d-%m-%Y') AS add_date"),
                'lotteries.lot_name',
                'winning_numbers.number_win',
                'users.username',
                'lotteries.lot_colorcode'
            )
            ->join('lotteries', 'winning_numbers.lot_id', '=', 'lotteries.lot_id')
            ->join('users', 'users.user_id', '=', 'winning_numbers.added_by')
            ->where('lotteries.user_added_id', $thisadmin)
            ->orderBy('winning_numbers.win_id', 'DESC')
            ->limit(50)
            ->get();



        return response()->json([
            'msg' => 'Seller-specific action performed',
            'success' => true,
            'data' => $winningList

        ]);
    }





    public function getWinningOrders(Request $request)
    {

        $user = auth()->user();
        //dd($user->user_role);
        switch ($user->user_role) {
            case 'seller':
                return $this->sellerWinningList($user);
                break;
            case 'manager':
                return $this->managerWinningList($user);
                break;
            case 'admin':
                return $this->adminWinningList($user);
                break;

            default:
                return response()->json(['error' => 'User Role not defined']);
        }
    }



    protected function sellerWinningList($user)
    {
        // Implement seller-specific logic here
        $userId = $user->user_id;
        $user = DB::table('users')->where('user_id', $userId)->first();

        if ($user) {
            $adminUserId = $this->getAdminUserId($user);
            //dd($adminUserId);
            $lotteries = DB::table('lotteries')->where('user_added_id', $adminUserId)->pluck('lot_id')->toArray();
            //$lotIds = implode(',', $lotteries);
            //dd($lotIds);
            $query = DB::table('order_item')
                ->select(
                    'lotteries.lot_name',
                    DB::raw("CAST(orders.order_id AS CHAR) AS ticket_id"),
                    'orders.client_name',
                    'orders.client_contact',
                    'order_item.lot_number',
                    DB::raw("CAST(order_item.winning_amount AS CHAR) AS winning_amount"), // Cast winning_amount to CHAR
                    DB::raw("CAST(order_item.order_item_id AS CHAR) AS order_item_id"),
                    DB::raw("DATE_FORMAT(orders.adddatetime, '%d-%m-%Y %H:%i:%s') AS adddatetime"),
                    'users.username AS sellername',
                    'users.added_user_id as useraddedID',
                    'lotteries.lot_colorcode',
                    'm.username AS managername',
                    DB::raw("CASE WHEN order_item.transaction_paid_id IS NULL THEN '0' ELSE 1 END AS paidthis")
                )
                ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
                ->join('lotteries', 'lotteries.lot_id', '=', 'order_item.product_id')
                ->join('users', 'users.user_id', '=', 'orders.user_id')
                ->join('users as m', 'm.user_id', '=', 'users.added_user_id') // Ensure $userId is properly used
                ->where('order_item.lottery_gone', 1)
                ->where('order_item.winning_amount', '>', 0)
                ->where('orders.user_id', '=', $userId)
                ->whereIn('lotteries.lot_id', $lotteries)
                ->orderBy('orders.order_id', 'DESC');

            $sql = $query->get();



            return response()->json([
                'msg' => 'Seller-specific action performed',
                'success' => true,
                'data' => $sql

            ]);
        }


        return response()->json(['message' => 'Seller-specific action performed']);
    }

    protected function managerWinningList($user)
    {
        // Implement seller-specific logic here
        $userId = $user->user_id;
        $user = DB::table('users')->where('user_id', $userId)->first();
        //dd($user->added_user_id);
        if ($user) {
            // Fetch seller user IDs correctly
            $sellerUserIds = DB::table('users')->where('added_user_id', $userId)->where('user_role', 'seller')->pluck('user_id')->toArray();
            $userAdmin = DB::table('users')->where('user_id', $user->added_user_id)->first();
            // dd($userAdmin);
            // Fetch lotteries according to seller user IDs
            $lotteries = DB::table('lotteries')->where('user_added_id', $userAdmin->user_id)->pluck('lot_id')->toArray();
            // dd($lotteries);
            // Query to get the winning list
            $query = DB::table('order_item')
                ->select(
                    'lotteries.lot_name',
                    DB::raw("CAST(orders.order_id AS CHAR) AS ticket_id"),
                    'orders.client_name',
                    'orders.client_contact',
                    'order_item.lot_number',
                    DB::raw("CAST(order_item.winning_amount AS CHAR) AS winning_amount"),
                    DB::raw("CAST(order_item.order_item_id AS CHAR) AS order_item_id"),
                    DB::raw("DATE_FORMAT(orders.adddatetime, '%d-%m-%Y %H:%i:%s') AS adddatetime"),
                    'users.username AS sellername',
                    'users.added_user_id as useraddedID',
                    'lotteries.lot_colorcode',
                    'm.username AS managername',
                    DB::raw("CASE WHEN order_item.transaction_paid_id IS NULL THEN '0' ELSE 1 END AS paidthis")
                )
                ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
                ->join('lotteries', 'lotteries.lot_id', '=', 'order_item.product_id')
                ->join('users', 'users.user_id', '=', 'orders.user_id')
                ->join('users as m', 'm.user_id', '=', 'users.added_user_id') // Ensure $userId is properly used
                ->where('order_item.lottery_gone', 1)
                ->where('order_item.winning_amount', '>', 0)
                ->whereIn('orders.user_id', $sellerUserIds) // Use seller user IDs here
                ->whereIn('lotteries.lot_id', $lotteries)
                ->orderBy('orders.order_id', 'DESC');

            $sql = $query->get();

            return response()->json([
                'msg' => 'Manager-specific action performed',
                'success' => true,
                'data' => $sql
            ]);
        }

        return response()->json(['message' => 'Manager-specific action performed']);
    }

    protected function adminWinningList($user)
    {
        $adminUserId = $user->user_id;
        //dd($adminUserId);
        $query = DB::table('order_item')
            ->select(
                'lotteries.lot_name',
                DB::raw("CAST(orders.order_id AS CHAR) AS ticket_id"),
                'orders.client_name',
                'orders.client_contact',
                'order_item.lot_number',
                DB::raw("CAST(order_item.winning_amount AS CHAR) AS winning_amount"), // Cast winning_amount to CHAR
                DB::raw("CAST(order_item.order_item_id AS CHAR) AS order_item_id"),
                DB::raw("DATE_FORMAT(orders.adddatetime, '%d-%m-%Y %H:%i:%s') AS adddatetime"),
                'users.username AS sellername',
                'users.added_user_id as useraddedID',
                'lotteries.lot_colorcode',
                'm.username AS managername',
                DB::raw("CASE WHEN order_item.transaction_paid_id IS NULL THEN '0' ELSE 1 END AS paidthis")
            )
            ->join('orders', 'order_item.order_id', '=', 'orders.order_id')
            ->join('lotteries', 'lotteries.lot_id', '=', 'order_item.product_id')
            ->join('users', 'users.user_id', '=', 'orders.user_id')
            ->join('users as m', 'm.user_id', '=', 'users.added_user_id')
            ->where('order_item.lottery_gone', 1)
            ->where('order_item.winning_amount', '>', 0)
            ->where('lotteries.user_added_id', $adminUserId)
            ->orderBy('orders.order_id', 'DESC');




        $sql = $query->get();



        return response()->json([
            'msg' => 'Seller-specific action performed',
            'success' => true,
            'data' => $sql

        ]);
    }

    public function deleteWinningNumber(Request $request)
    {
        DB::beginTransaction();
        try {

            $user = auth()->user();
            $winId = $request->input('win_id');

            $winningNumber = DB::table('winning_numbers')
                ->where('win_id', $winId)
                ->first();

            $orderItems = DB::table('order_item')
                ->where('product_id', $winningNumber->lot_id)
                ->whereDate('adddatetime', $winningNumber->add_date)
                ->update([
                    'lottery_gone' => 0,
                    'winning_amount' => 0
                ]);

            DB::table('winning_numbers')
                ->where('win_id', $winId)
                ->delete();

            DB::commit();
            return response()->json(['success' => true, 'msg' => 'Winning number deleted'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 400);
        }
    }

    private function getAdminUserId($user)
    {
        if ($user->user_role == 'admin') {
            return $user->user_id;
        }

        $addedUser = DB::table('users')->where('user_id', $user->added_user_id)->first();

        if ($addedUser->user_role == 'admin') {
            return $addedUser->user_id;
        }

        $addedUser2 = DB::table('users')->where('user_id', $addedUser->added_user_id)->first();
        return $addedUser2->user_id;
    }
}
