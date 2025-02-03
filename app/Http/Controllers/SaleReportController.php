<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Lottery;
use App\Models\User;
use Carbon\Carbon;

class SaleReportController extends Controller
{
    public function saleReport(Request $request)
{
    $lotId = $request->input('lottery');
    $managerIds = $request->input('manager_ids', []); // Provide an empty array as default value

    $userId = auth()->user()->user_id;
    $user = auth()->user();
    $userRole = $user->user_role;

    $fromDate = $request->input('fromdate');
    $toDate = $request->input('todate');

    // Parse the input dates using Carbon
    $fromDateCarbon = Carbon::createFromFormat('j M, Y - H:i', $fromDate);
    $toDateCarbon = Carbon::createFromFormat('j M, Y - H:i', $toDate);

    // Get the date portion (YYYY-MM-DD)
    $fromDate = $fromDateCarbon->format('Y-m-d');
    $toDate = $toDateCarbon->format('Y-m-d');

    $lottery = Lottery::find($lotId);
    if (!$lottery) {
        return response()->json(['error' => 'Invalid lottery.'], 404);
    }

    $sellerIds = [];
    $users = [];

    if (!empty($managerIds)) {
        foreach ($managerIds as $managerId) {
            if ($userRole === 'admin') {
                if (!$managerId) {
                    return response()->json(['error' => 'Manager ID is required for admin role.'], 400);
                }
                // Check if the managerId belongs to a manager or seller
                $user = User::where('user_id', $managerId)->first();
    
                if ($user && $user->user_role === 'manager') {
                    // Get sellers under this manager
                    $sellers = User::where('added_user_id', $managerId)->pluck('user_id')->toArray();
                    $sellerIds = array_merge($sellerIds, $sellers);
                } else {
                    // If it's a seller, add the seller's ID directly
                    $sellerIds[] = $managerId;
                }
            } elseif ($userRole === 'manager') {
                // Check if the managerId belongs to a manager or seller
                $user = User::where('user_id', $managerId)->first();
    
                if ($user && $user->user_role === 'manager') {
                    // Get sellers under this manager
                    $sellers = User::where('added_user_id', $managerId)->pluck('user_id')->toArray();
                    $sellerIds = array_merge($sellerIds, $sellers);
                } else {
                    // If it's a seller, add the seller's ID directly
                    $sellerIds[] = $managerId;
                }
            } else {
                // For sellers, just add their own user_id
                $sellerIds[] = $userId;
            }
            $managers = User::where('user_id', $managerId)->first();
            $users[] = $managers;
        }
    } else {
        // If no manager IDs provided, simply add the seller's user ID
        $sellerIds[] = $userId;
    }

    if (empty($sellerIds)) {
        return response()->json(['error' => 'No sellers found for the specified manager.'], 404);
    }

    $salesData = [];

    for ($i = 0; $i <= 99; $i++) {
        $numberN = sprintf("%02d", $i);
        $salesData['numberlist'][$numberN] = $this->getAmount($sellerIds, $lotId, $numberN, $fromDate, $toDate);
    }

    $salesData['totalSold'] = $this->getTotalSold($sellerIds, $lotId, $fromDate, $toDate);
    $salesData['commission'] = $this->getCommission($sellerIds, $lotId, $fromDate, $toDate, $user->commission);
    $salesData['winnings'] = $this->getWinnings($sellerIds, $lotId, $fromDate, $toDate);
    $salesData['balance'] = $this->getBalance($sellerIds, $lotId, $fromDate, $toDate, $user->commission);
    $salesData['winningNumbersTotal'] = $this->getWinningNumbersTotal($sellerIds, $lotId, $fromDate, $toDate);

    $salesData['lotteryName'] = $lottery->lot_name;
    $salesData['date'] = now()->format('d-m-Y h:i');
    $salesData['users'] = $users;

    $jsonResponse = [
        'success' => true,
        'msg' => 'Get Successfully',
        'data' => $salesData
    ];

    // return response()->json($jsonResponse, 200);
    return view('saleReport', ['data' => $salesData]);
}

private function getAmount($sellerIds, $lotId, $numberN, $fromDate, $toDate)
{
    $ordersList = Order::whereIn('user_id', $sellerIds)
        ->whereBetween('order_date', [$fromDate, $toDate])
        ->pluck('order_id')
        ->toArray();

    $totalSold = OrderItem::where('product_id', $lotId)
        ->whereIn('order_id', $ordersList)
        ->where('lot_number', $numberN)
        ->sum('lot_amount');

    return number_format($totalSold * 20);
}

private function getTotalSold($sellerIds, $lotId, $fromDate, $toDate)
{
    $ordersList = Order::whereIn('user_id', $sellerIds)
        ->whereBetween('order_date', [$fromDate, $toDate])
        ->pluck('order_id')
        ->toArray();

    $totalSold = OrderItem::where('product_id', $lotId)
        ->whereIn('order_id', $ordersList)
        ->sum('lot_amount');

    return $totalSold * 20 * 0.05;
}

private function getCommission($sellerIds, $lotId, $fromDate, $toDate, $commission)
{
    $totalSold = $this->getTotalSold($sellerIds, $lotId, $fromDate, $toDate);

    return number_format(($totalSold / 100) * $commission , 2);
}

private function getWinnings($sellerIds, $lotId, $fromDate, $toDate)
{
    $ordersList = Order::whereIn('user_id', $sellerIds)
        ->whereBetween('order_date', [$fromDate, $toDate])
        ->pluck('order_id')
        ->toArray();

    $winnings = OrderItem::whereIn('order_id', $ordersList)
        ->where('product_id', $lotId)
        ->whereDate('adddatetime', '>=', $fromDate)
        ->whereDate('adddatetime', '<=', $toDate)
        ->sum('winning_amount');

    return $winnings;
}

private function getBalance($sellerIds, $lotId, $fromDate, $toDate, $commission)
{
    $totalSold = $this->getTotalSold($sellerIds, $lotId, $fromDate, $toDate);
    $commission = $this->getCommission($sellerIds, $lotId, $fromDate, $toDate, $commission);
    $winnings = $this->getWinnings($sellerIds, $lotId, $fromDate, $toDate);

    $totalSold = floatval($totalSold); // Convert to float
    $commission = floatval($commission); // Convert to float
    $winnings = floatval($winnings); // Convert to float

    if (is_numeric($totalSold) && is_numeric($commission) && is_numeric($winnings)) {
        $result = number_format(($totalSold - $commission - $winnings), 2);
        
       $balance = $totalSold - $commission - $winnings;
        return $balance;
        
    } else {
        return "0";
    }
}

private function getWinningNumbersTotal($sellerIds, $lotId, $fromDate, $toDate)
{
    $ordersList = Order::whereIn('user_id', $sellerIds)
        ->whereBetween('order_date', [$fromDate, $toDate])
        ->pluck('order_id')
        ->toArray();

    $winningNumbersTotal = OrderItem::whereIn('order_id', $ordersList)
        ->where('product_id', $lotId)
        ->whereDate('adddatetime', '>=', $fromDate)
        ->whereDate('adddatetime', '<=', $toDate)
        ->sum('winning_amount');

    return $winningNumbersTotal;
}


}
