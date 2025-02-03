<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;

class OrderController extends Controller
{

    // public function createOrder(Request $request)
    // {
    //     $url = 'abvcd';
    //     $today = now()->toDateString();
    //     $data = $request->input('data');

    //     // Check if the user is authenticated
    //     if (auth()->check()) {
    //         $user = auth()->user();
    //         $userId = $user->user_id;

    //         if (!empty($data)) {
    //             // Rest of your code...

    //             $order = new Order([
    //                 'order_date' => $today,
    //                 'client_name' => $request->input('name'),
    //                 'client_contact' => $request->input('number'),
    //                 'user_id' => $userId,
    //                 'sub_total' => 0,
    //             ]);

    //             // Rest of your code...

    //             $response = [
    //                 'url' => $url,

    //                 'msg' => 'Lottery Sold Successfully',
    //                 'is_Status' => 1,
    //             ];
    //         } else {
    //             $response = [
    //                 'url' => '',
    //                 'orderID' => 0,
    //                 'msg' => 'Error: No data provided.',
    //                 'is_Status' => 0,
    //             ];
    //         }
    //     } else {
    //         $response = [
    //             'url' => '',
    //             'orderID' => 0,
    //             'msg' => 'Error: User not authenticated.',
    //             'is_Status' => 0,
    //         ];
    //     }

    //     return response()->json($response);
    // }

    public function createOrder(Request $request)
{
    $today = now()->toDateString();
date_default_timezone_set("America/Guatemala");

// Current time in Guatemala
$serverTimeWithGuatemala = new DateTime(now()->format('H:i:s'));

    $data = $request->input('data');
    
    foreach($data as $item){
        // Fetch lottery information from the database
        $lottery = DB::table('lotteries')
    ->where('lot_id', $item['loteryId'])
    ->first();
    
    // Open and close times from the lottery object (in 'H:i:s' format)
    $lotteryOpenTime = DateTime::createFromFormat('H:i:s', $lottery->lot_opentime);
    $lotteryCloseTime = DateTime::createFromFormat('H:i:s', $lottery->lot_closetime);

        
        if ($serverTimeWithGuatemala < $lotteryOpenTime || $serverTimeWithGuatemala > $lotteryCloseTime) {
            return response()->json([
                'success' => false,
                'msg' => $lottery->lot_name . ' Lottery is closed.',
            ], 400);
        }
    }

    if (auth()->check()) {
        $user = auth()->user();
        $userId = $user->user_id;

        if (!empty($data)) {
            // Create Order without saving
            $order = new Order([
                'order_date' => $today,
                'client_name' => $request->input('name'),
                'client_contact' => $request->input('number'),
                'user_id' => $userId,
                'sub_total' => 0,
            ]);

            //dd( $order);

            $order->save(); // Save Order

            $currentOrderId = $order->order_id;

            $grandTotal = 0;

            foreach ($data as $item) {
                $calculated = $item['frac'];
                // Create OrderItem without saving
                $orderItem = new OrderItem([
                    'order_id' => $currentOrderId,
                    'product_id' => $item['loteryId'],
                    'product_name' => $item['loteryName'],
                    'lot_number' => $item['number'],
                    'lot_frac' => $calculated,
                    'lot_amount' => $item['quator'],
                ]);

                $orderItem->save(); // Save OrderItem

                if (is_numeric($item['quator'])) {
                    $grandTotal += $item['quator'];
                } else {
                    $grandTotal += 0;
                }
            }

            $transaction = Transaction::create([
                'debit' => 0,
                'credit' => $grandTotal,
                'balance' => 0,
                'seller_id' => $userId,
                'transaction_remarks' => 'Lottery sold.' . $currentOrderId,
            ]);

            $orderId = $currentOrderId;

            Order::where('order_id', $orderId)->update([
                'sub_total' => $grandTotal,
                'grand_total' => $grandTotal,
                'transaction_id' => $transaction->transaction_id,
            ]);
            // dd($currentOrderId);
            //get order
            $orderDetails = $this->getOrderDetails($currentOrderId);



            $response = [
                'success' => true,
                'msg' => 'Lottery Added Successfully',
                 'orderID' => (string) $orderId, // Convert $orderId to string
                'lotteryData' => $orderDetails,
            ];
        } else {
            $response = [
                'success' => false,
                'msg' => 'Error',
                'orderID' => '',
                'lotteryData' => '',
            ];
        }
    } else {
        $response = [

            'success' => false,
            'msg' => 'Error: User not authenticated.',
            'orderID' => '',
            'lotteryData' => '',
        ];
    }

    return response()->json($response);
}



public function deleteOrder(Request $request, $id)
{

    $user = Auth()->user();
    // Retrieve order_id from the request
    $order_id = $id;

    // Delete related records
    try {
        OrderItem::where('order_id', $order_id)->delete();
        $order = Order::find($order_id);
        if ($order) {
            Transaction::where('transaction_id', $order->transaction_id)->delete();
            $order->delete();
            DB::table('notifications')->insert([
                'added_user_id' => $user->user_id,
                'user_id' => $user->added_user_id, // Assuming the order has a user_id field
                'notification_message' => $user->username . ' ' . 'just canceled an Order.',
            ]);
        }
    } catch (\Exception $e) {
        // If an error occurs during deletion, return an error response
        return response()->json([
            'success' => false,
            'msg' => 'Error: User not authenticated.',
            'error' => 'Failed to delete order'], 500);
    }

    // Return a success message
    return response()->json([
        'success' => true,

        'msg' => 'Order and related records deleted successfully']);
}





public function orderList(Request $request){
    // Retrieve orders from the database
    $user_id = auth()->user();
    // Get the current time
$currentDateTime = Carbon::now();

// $orders = Order::select(
//     'order_id', 'order_date', 'client_name', 'client_contact',
//     'sub_total', 'grand_total',
//     \DB::raw("CASE WHEN TIMESTAMPDIFF(MINUTE, adddatetime, '$currentDateTime') <= 5 THEN 1 ELSE 0 END as is_deleted")
// )
// ->with(['orderItems' => function ($query) {
//     $query->select('order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount');
// }])
// ->where('user_id', $user_id->user_id)
// ->orderBy('orders.order_id', 'DESC')
// ->limit(100)
// ->get();

$orders = Order::select(
    'order_id', 'order_date', 'client_name', 'client_contact',
    'sub_total', 'grand_total',
    \DB::raw("CASE WHEN TIMESTAMPDIFF(MINUTE, adddatetime, NOW()) <= 5 THEN '1' ELSE '0' END as is_deleted")
)
->with(['orderItems' => function ($query) {
    $query->select('order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount');
}])
->where('user_id', $user_id->user_id)
->orderBy('orders.order_id', 'DESC')
->limit(100)
->get();






    // You can then pass the $orders variable to your view or process it further as needed

    // For example, if you want to return JSON response
    return response()->json([
        'success' => true,
        'msg' => 'order get ',
        'orders' => $orders]);
}


public function printOrder(Request $request, $id)
{
    // Fetch the order details
    $currentOrderId = $id;
    $orderDetails = $this->getOrderDetails($currentOrderId);

    // Generate the QR code URL
    $url = route('printOrder', ['id' => $id]); // Replace 'printOrder' with the route name if it has a name
    $qrCode = QrCode::size(200)->generate($url);

    // Prepare response data
    $response = [
        'success' => true,
        'msg' => 'Lottery get Successfully',
        'orderID' => $currentOrderId,
        'lotteryData' => $orderDetails,
        'qrCode' => $qrCode,
    ];

    return view('print', ['data' => $response]);
}


public function orderprint(Request $request , $id)
{
    // Assuming $currentOrderId is available
    $currentOrderId = $id; // Replace with your actual logic to get the current order ID

    // Assuming $orderId is available and contains the ID of the current order
    $orderId = $currentOrderId; // Replace with your actual logic to get the order ID

    // Assuming $orderDetails is available and contains the details of the current order
    // Replace this line with your logic to get order details
    $orderDetails = $this->getOrderDetails($currentOrderId);

    // Construct the response
    $response = [
        'success' => true,
        'msg' => 'Lottery get Successfully',
        'orderID' => $orderId,
        'lotteryData' => $orderDetails,
    ];

    // Return the response
    return response()->json($response);
}




function getOrderDetails($orderId) {
    $orderDetails = Order::select('order_id', 'user_id', 'order_date', 'client_name', 'client_contact', 'sub_total', 'grand_total','adddatetime')
        ->with(['orderItems' => function ($query) {
            $query->select('order_item_id', 'order_id', 'product_id', 'winning_amount', 'transaction_paid_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount');
        }])
        ->where('order_id', $orderId)
        ->first();

    if ($orderDetails) {
        // Convert order details attributes to strings
        foreach ($orderDetails->getAttributes() as $key => $value) {
            $orderDetails->{$key} = (string) $value;
        }

        // Convert order items to strings
        foreach ($orderDetails->orderItems as $orderItem) {
            foreach ($orderItem->getAttributes() as $key => $value) {
                $orderItem->{$key} = (string) $value;
            }
        }

        // Optionally, if you want to return an array instead of an object, you can convert it like this:
        $orderDetails = $orderDetails->toArray();
    }

    return $orderDetails;
}


}
