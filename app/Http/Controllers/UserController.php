<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RequestUser;
use App\Mail\RequestConfirmation;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Mail\ForgotPasswordMail;

class UserController extends Controller
{
    //
    public function getManagers(){
        try{
            $managers = User::select('user_id', 'username')->where('user_role', 'manager')->where('status', 1)->get();
            
            return response()->json(['success' => true, 'data' => $managers]);
            
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    public function getNotifications(){
        try{
            
            $user = Auth()->user();
            
            $notifications = DB::table('notifications')->where('user_id', $user->user_id)->orderBy('add_datetime', 'DESC')->get();
            
            if ($notifications->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No notifications found', 'data' => []], 200);
            }
            
            return response()->json(['success' => true, 'data' => $notifications], 200);
            
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    public function getUserCredit()
    {
        $user = Auth()->user();
        
        if($user->user_role == 'superadmin'){
            $totalCredit = [
                'user_amount' => '0',
                'user_frac' => '0',
            ];
            
            $usedCredit = [
                'user_amount' => '0',
                'user_frac' => '0',
            ];
            
            $availableCredit = [
                'user_amount' => '0',
                'user_frac' => '0',
            ];
            
            return response()->json(['success' => true, 'totalCredit' => $totalCredit, 'usedCredit' => $usedCredit, 'availableCredit' => $availableCredit]);
        }elseif($user->user_role == 'admin' || $user->user_role == 'manager'){
            $sumUserAmount = User::where('added_user_id', $user->user_id)->sum('user_amount');
            $sumUserFrac = User::where('added_user_id', $user->user_id)->sum('user_frac');
            
            $totalCredit = [
                'user_amount' => (string)$user->user_amount,
                'user_frac' => (string)$user->user_frac,
            ];
            
            $usedCredit = [
                'user_amount' => (string)$sumUserAmount, // Convert to string
                'user_frac' => (string)$sumUserFrac,     // Convert to string
            ];
            
            $availableCredit = [
                'user_amount' => (string)($user->user_amount - $sumUserAmount), // Convert to string
                'user_frac' => (string)($user->user_frac - $sumUserFrac),       // Convert to string
            ];
            
            return response()->json(['success' => true, 'totalCredit' => $totalCredit, 'usedCredit' => $usedCredit, 'availableCredit' => $availableCredit]);
            
        }else{
            return response()->json(['success' => false, 'msg' => 'coming soon seller']);
        }
        
    }
    
    public function uploadProfileImage(Request $request)
    {
        try{
            $loggedInUser = auth()->user();
            
            $user = User::where('user_id', $loggedInUser->user_id)->first();
            
            if ($request->hasFile('userImage')) {
                    // Get the path of the image from the animal record
                    $imagePath = public_path($user->user_image); // Get the full image path

                    // Delete the image file if it exists
                    if (file_exists($imagePath) && $user->user_image != null) {
                        unlink($imagePath); // Delete the image from the file system
                    }

                    $image = $request->file('userImage');
                    // Store the image in the 'animal_images' folder and get the file path
                    $imagePath = $image->store('user_images', 'public'); // stored in 'storage/app/public/animal_images'
                    $imageFullPath = 'storage/' . $imagePath;
                    $user->user_image = $imageFullPath;
                    $user->save();
                    
                    return response()->json(['success' => true, 'message' => 'Profile Image is updated', 'user' => $user], 200);
                }else{
                    return response()->json(['success' => false, 'message' => 'userImage is required'], 400);
                }
            
        }catch(\Exception $e){
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    public function resetPassword(Request $request)
    {
        try {
            $password = $request->input('password');
            $otp = $request->input('otp');
    
            // Find the user with the matching OTP and active status
            $user = User::where('user_otp', $otp)->where('status', 1)->first();
    
            if (!$user) {
                return response()->json(['success' => false, 'msg' => 'Invalid OTP or inactive user'], 200);
            }
    
            // Update the user's password in md5 format and remove the OTP
            $user->password = md5($password);
            $user->user_otp = null;
            
            $user->save();
    
            return response()->json(['success' => true, 'msg' => 'Password updated successfully'], 200);
    
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $email = $request->input('email');
    
            // Check if the user exists
            $user = DB::table('users')->where('email', $email)->where('status', 1)->first();
    
            if (!$user) {
                return response()->json(['success' => false, 'msg' => 'User not found'], 200);
            }
    
            // Generate a 4-digit OTP
            $otp = rand(1000, 9999);
    
            // Update the user's OTP in the database
            DB::table('users')->where('email', $email)->update(['user_otp' => $otp]);
    
            // Send OTP to user's email
            Mail::to($email)->send(new ForgotPasswordMail($otp));
    
            return response()->json(['success' => true, 'msg' => 'OTP sent to your email', 'otp' => $otp], 200);
    
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], 400);
        }
    }

    public function addusers(Request $request ,  $user_id = null)
    {
        //dd($request);
        if ($request->filled(['email', 'password','user_role'])) {

            $user = auth()->user();
            
            DB::beginTransaction(); // Start the transaction

            try {
                $userData = [
                    'username'      => $request->input('username'),
                    'email'         => $request->input('email'),
                    'password'      => md5($request->input('password')),
                    'phone'         => $request->input('phone'),
                    'user_role'     => strtolower($request->input('user_role')),
                    'commission'    => $request->input('commission'),
                    'added_user_id' => $request->filled('assign_to') ? $request->input('assign_to') : $user->user_id,
                    'address'       => $request->input('address'),
                    'user_amount'   => $request->input('user_amount'),
                    'user_frac'     => $request->input('user_frac'),
                ];

                if ($request->filled('req_user_id')) {
                    // Assuming 'request_user' is your table for storing requests
                    // Remove the request entry if req_user_id is provided
                    // Replace 'request_user' with your actual table name
                    DB::table('request_user')->where('req_user_id', $request->input('req_user_id'))->delete();
                }
                if(!empty($user_id)){
                    $sumUserAmount = User::where('added_user_id', $user->user_id)->sum('user_amount');
                    $sumUserFrac = User::where('added_user_id', $user->user_id)->sum('user_frac');
                    
                    
                        $availableUserAmount = $user->user_amount - $sumUserAmount;
                        $availableUserFrac = $user->user_frac - $sumUserFrac;
                    
                    
                    // Check if both credits are greater than 0 before inserting
                    if($user->user_role == 'superadmin'){
                        DB::table('users')->where('user_id', $user_id)->update($userData);
                    }elseif ($availableUserAmount > 0 && $availableUserFrac > 0) {
                        DB::table('users')->where('user_id', $user_id)->update($userData);
                    } else {
                        DB::rollBack(); // Something went wrong
                        return response()->json([ 'success' => false, 'msg' => 'Insufficient credit'], 400);
                    }
                }else{
                // Insert user data into the 'users' table
                
                $sumUserAmount = User::where('added_user_id', $user->user_id)->sum('user_amount');
                $sumUserFrac = User::where('added_user_id', $user->user_id)->sum('user_frac');
                
                $availableUserAmount = $user->user_amount - $sumUserAmount;
                $availableUserFrac = $user->user_frac - $sumUserFrac;
                    
                    
                    // Check if both credits are greater than 0 before inserting
                    if($user->user_role == 'superadmin'){
                        DB::table('users')->insert($userData);
                    }elseif ($availableUserAmount >= 0 && $availableUserFrac >= 0) {
                    DB::table('users')->insert($userData);
                } else {
                    DB::rollBack(); // Something went wrong
                    return response()->json([ 'success' => false, 'msg' => 'Insufficient credit'], 400);
                }
            }
            
            if ($request->filled('assign_to') && empty($user_id)) {
                DB::table('notifications')->insert([
                    'user_id'       => $request->input('assign_to'),
                    'added_user_id' => $user->user_id,
                    'notification_message'       => 'A new user named '. $request->input('username') . ' is added.',
                ]);
            }
            
            DB::commit(); // All good
            
                $response = [
                    'success' => true,
                    'msg'       => ($user_id !== null) ? 'User Updated Successfully' : 'User Added Successfully',

                ];
            } catch (\Exception $e) {
                
                DB::rollBack(); // Something went wrong
                
                $response = [
                    'success' => false,
                    'msg'       => $e->getMessage(),
                ];
            }

            return response()->json($response);
        } else {
            DB::rollBack(); // Something went wrong
            return response()->json([
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ]);
        }
    }




    public function requestUser(Request $request ){
        if ($request->filled(['username','useremail', 'password'])) {
            try {
                $requestData = [
                    'username'  => $request->input('username'),
                    'email'     => $request->input('useremail'),
                    'password'  => $request->input('password'),
                    'phone'     => $request->input('phone'),
                    'user_role' => strtolower($request->input('user_role')),
                    'address'   => $request->input('address'),
                ];
                
                if ($request->hasFile('cnic_front')) {
                $cnicFrontPath = $request->file('cnic_front')->store('public/cnic_images');
                $requestData['cnic_front'] = Storage::url($cnicFrontPath);
                }
    
                if ($request->hasFile('user_image')) {
                    $cnicBackPath = $request->file('user_image')->store('public/user_images');
                    $requestData['user_image'] = Storage::url($cnicBackPath);
                }

                // Insert request user data into the 'request_user' table
                RequestUser::create($requestData);

                // Call the sendConfirmationEmail function
        //$this->sendConfirmationEmail($request);
                $response = [
                    'success' => true,
                    'msg'       => 'Information added, we will update you soon',
                ];
            } catch (\Exception $e) {
                $response = [
                    'success' => false,
                    'msg'       => $e->getMessage(),
                ];
            }

            return response()->json($response);
        } else {
            return response()->json([
                'success' => false,
                'msg'       => 'Invalid request parameters',
            ]);
        }
    }


    public function requestUserList(Request $request  ){


        $requestedUsers = RequestUser::orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'msg' => 'All requested List',
        'data' => $requestedUsers]);


    }



     function sendConfirmationEmail(Request $request)
{
    // Assume $userData contains the necessary user information


    $userData = [
        'username' => $request->input('username'),
        'email' => $request->input('useremail'),
        'phone' =>  $request->input('phone'),
        'user_role' =>  $request->input('userrole'),
        'address' => $request->input('address'),
    ];

    // Send the email
    Mail::to($userData['email'])->send(new RequestConfirmation($userData));

    // Optionally, you can check if the email was sent successfully
    if (count(Mail::failures()) > 0) {
        return response()->json([
            'is_status' => 0,
            'msg' => 'Failed to send confirmation email',
        ]);
    }

    return response()->json([
        'is_status' => 1,
        'msg' => 'Confirmation email sent successfully',
    ]);
}

// user list based on user role

public function userList(Request $request)
{
    $userId = auth()->user()->user_id;
    $loggedInUser = User::find($userId);

    if ($loggedInUser) {
        if ($loggedInUser->user_role === 'superadmin') {
            $users = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'user_amount', 'user_frac', 'commission', 'added_user_id','status')
                ->where('user_id', '!=', $userId)
                ->orderBy('user_role', 'ASC')
                ->get();
            //dd($users);
            $userTree = $this->buildUserTree($users->toArray(), null);

            $jsonResponse = [
                'success' => true,
                'msg' => 'Get Successfully',
                'data' => $userTree
            ];
        } else {
            $admins = User::select('user_id', 'username', 'email', 'phone', 'user_role', 'user_amount', 'user_frac', 'commission','status')
            ->where(function($query) use ($userId) {
                $query->where('added_user_id', $userId)
                      ->orWhere('user_id', $userId);
            })
            ->orderBy('user_role', 'ASC')
            ->get();


            $adminsArray = $admins->toArray();

            $jsonResponse = [
                'success' => true,
                'msg' => 'Get Successfully',
                'data' => $adminsArray
            ];
        }

        return response()->json($jsonResponse);
    }
}




public function changePassword(Request $request)
{
    $validator = validator($request->all(), [
        'current_password' => 'required',
        'new_password' => 'required|min:8',
        'confirm_password' => 'required|same:new_password',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => true,
            'msg' => $validator->errors()->first(),
            'error' => 'error',

        ], 422);
    }

    if(!empty($request->user_id)){
        $userId = $request->user_id;
        $user = User::find($userId);
    }else{
    $user = auth()->user();
    }
    // Check if the current password matches the one in the database (for MD5 hashed passwords)
    if (md5($request->current_password) !== $user->password) {
        return response()->json([
            'success' => true,
            'msg' => 'Current password is incorrect.',
            'error' => 'error',

        ], 401);
    }



    // Update the user's password
    $user->update([
        'password' => md5($request->new_password),
    ]);

    return response()->json([
        'success' => true,
        'msg' => 'Password changed successfully.',
        'user_id' => $user->user_id,
    ]);


}







// private funcations

private function buildUserTree($users)
{
    $userHash = [];

    // Create a hash table using user_id as keys
    foreach ($users as $user) {
        $userHash[$user['user_id']] = $user;
    }

    $tree = [];

    foreach ($users as $user) {
        if ($user['user_role'] === 'admin') {
            // Admin is a root element
            $tree[] = &$userHash[$user['user_id']];
        } elseif ($user['user_role'] === 'manager' && isset($userHash[$user['added_user_id']])) {
            // Manager is a child of admin
            $parent = &$userHash[$user['added_user_id']];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = &$userHash[$user['user_id']];
        } elseif ($user['user_role'] === 'seller' && isset($userHash[$user['added_user_id']])) {
            // Seller is a child of manager
            $parent = &$userHash[$user['added_user_id']];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = &$userHash[$user['user_id']];
        }

        // Ensure every user has a 'children' array
        if (!isset($userHash[$user['user_id']]['children'])) {
            $userHash[$user['user_id']]['children'] = [];
        }
    }

    return $tree;
}

//edit user only commsion and status


public function editUser(Request $request, $userId)
{
    try {
        $loggedInUser = Auth()->user();
        // Validate the incoming request data
        $validatedData = $request->validate([
            'commission' => 'required',
            'status' => 'required',
            'user_amount' => 'nullable',
            'user_frac' => 'nullable',
            'username' => 'nullable',
            'email' => 'nullable',
        ]);

        // Prepare the user data for update
        $userData = [
            'commission' => $validatedData['commission'],
            'status' => $validatedData['status'],
            'user_amount' => $validatedData['user_amount'],
            'user_frac' => $validatedData['user_frac'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
        ];
        
                $sumUserAmount = User::where('added_user_id', $loggedInUser->user_id)->sum('user_amount');
                $sumUserFrac = User::where('added_user_id', $loggedInUser->user_id)->sum('user_frac');
                
                $availableUserAmount = $loggedInUser->user_amount - $sumUserAmount;
                $availableUserFrac = $loggedInUser->user_frac - $sumUserFrac;
                    
                    $creditUser = User::where('user_id', $userId)->first();
                    // Check if both credits are greater than 0 before inserting
                    if($loggedInUser->user_role == 'superadmin'){
                        DB::table('users')
                        ->where('user_id', $userId)
                        ->update($userData);    
                    }elseif ($availableUserAmount > 0 && $availableUserFrac > 0) {
                    DB::table('users')
                        ->where('user_id', $userId)
                        ->update($userData);
                    }elseif($validatedData['user_amount'] <= $creditUser->user_amount && $validatedData['user_frac'] <= $creditUser->user_frac){
                        DB::table('users')
                        ->where('user_id', $userId)
                        ->update($userData);
                    } else {
                        return response()->json([ 'success' => false, 'msg' => 'Insufficient credit'], 400);
                    }

        // Update the user attributes using the DB facade



        // Return a response indicating success
        return response()->json([
            'success' => true,
            'msg' => 'User updated successfully',

        ], 200);
    } catch (\Exception $e) {
        // Log the error
        \Log::error('Error updating user: ' . $e->getMessage());

        // Return an error response
        return response()->json([
            'success' => false,
            'msg' => 'Failed to update user.'], 500);
    }
}



}
