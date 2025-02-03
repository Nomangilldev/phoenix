<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || md5($request->password) !== $user->password) {
            return response()->json(['msg' => 'Invalid credentials', 'success' => false], 401);
        }

        if($user || md5($request->password) === $user->password){
          if($user->status !== '1'){
            return response()->json(['error' => 'User Not Active', 'success' => false , 'msg' => 'User Not Active'], 401);
          }
           $token = $user->createToken('auth_token')->plainTextToken;

           return response()->json(['token' => $token, 'user' => $user, 'success' => true , 'msg' => 'User Login Successfully']);
        }

        return response()->json(['error' => 'Invalid credentials', 'success' => false ,  'msg' => 'Invalid credentials'], 401);

    }
}
