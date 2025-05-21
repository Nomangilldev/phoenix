<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Riddles;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiddlesController extends Controller
{


    // List all riddles
    public function index()
    {
        $user = Auth()->user();
        $baseUrl = url('/');
        if ($user->user_role == 'superadmin') {
            $riddles = Riddles::orderBy('rid_id', 'desc')->get();
        } elseif ($user->user_role == 'admin') {
            $riddles = Riddles::where('user_id', $user->user_id)->orderBy('rid_id', 'desc')->get();
        } else {
            $addedUser = User::where('user_id', $user->added_user_id)->first();
            if ($user->user_role == 'manager') {
                $riddles = Riddles::where('user_id', $addedUser->user_id)->orderBy('rid_id', 'desc')->get();
            } elseif ($user->user_role == 'seller') {
                $managerAddedUser = User::where('user_id', $addedUser->added_user_id)->first();
                $riddles = Riddles::where('user_id', $managerAddedUser->user_id)->orderBy('rid_id', 'desc')->get();
            }
        }

        // Add base URL to the rid_image field in each Riddles object
        // Add base URL to the rid_img field and convert user_id to string for each Riddles object
        $riddles->each(function ($riddle) use ($baseUrl) {
            $riddle->rid_img = $baseUrl . $riddle->rid_img;
            $riddle->user_id = (string) $riddle->user_id; // Convert user_id to string
        });

        // Check if the $riddles collection is empty
        if ($riddles->isEmpty()) {
            return response()->json([
                'success' => false,
                'msg' => 'No riddles found',
                'data' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'msg' => 'Riddles Get Successfully',
            'data' => $riddles
        ]);
    }



    public function destroy(Request $request, $rid_id)
    {
        // Find the Riddle instance by its ID
        $riddle = Riddles::findOrFail($rid_id);

        // Delete the found Riddle
        $riddle->delete();

        // Optionally, you can return a response indicating success
        return response()->json([
            'success' => true,
            'msg' => 'Riddles Deleted ',
        ]);
    }

    public function winingList()
    {
        $baseUrl = url('/');
        $user = auth()->user();

        $result = DB::table('winning_numbers')
            ->join('lotteries', 'lotteries.lot_id', '=', 'winning_numbers.lot_id')
            ->join('users', 'users.user_id', '=', 'winning_numbers.added_by')
            ->select('winning_numbers.*', 'lotteries.*', 'users.username') // Alias the users table as username
            ->orderBy('win_id', 'DESC')
            ->where("added_by", $user->user_id)
            ->limit(40)
            ->get();


        // If $result is empty, return response with success false
        if ($result->isEmpty()) {
            return response()->json([
                'success' => false,
                'msg' => 'No winning numbers found.',
                'data' => [],
            ], 200);
        }

        // Concatenate base URL with img_url in each result
        foreach ($result as $item) {
            $item->img_url = $baseUrl . $item->img_url;
        }

        $result = collect($result)->map(function ($item) {
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
            'msg' => 'Winning Number list',
            'data' => $result,
        ], 200);
    }






    public function store(Request $request,  $rid_id = null)
    {
        $user = auth()->user();

        $validatedData = $request->validate([
            'rid_title' => 'required',
            'rid_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Add validation rules for image files
        ]);

        // // Store the uploaded file in the storage/app/public directory
        // $imagePath = $request->file('rid_img')->store('riddle_images');

        // // Get the file name from the stored path
        // $imageName = basename($imagePath);


        $imgName = uniqid() . '.' . $request->file('rid_img')->getClientOriginalExtension();
        $request->file('rid_img')->storeAs('public/riddle_images', $imgName);
        $imgUrlForApi = Storage::url('riddle_images/' . $imgName);

        if ($rid_id !== null) {
            // Editing an existing riddle
            $riddleData = [
                'rid_title' => $validatedData['rid_title'],
                'rid_img' => $imgUrlForApi,
                'user_id' => $user->user_id,
                // Add other fields as needed
            ];

            DB::table('riddles')->where('rid_id', $rid_id)->update($riddleData);

            $riddle = DB::table('riddles')->where('rid_id', $rid_id)->first();
        } else {
            // Adding a new riddle
            $riddleData = [
                'rid_title' => $validatedData['rid_title'],
                'rid_img' => $imgUrlForApi,
                'user_id' => $user->user_id,
                // Add other fields as needed
            ];

            $rid_id = DB::table('riddles')->insertGetId($riddleData);

            $riddle = DB::table('riddles')->where('rid_id', $rid_id)->first();
        }

        // You can now use $riddle as needed


        return response()->json([
            'success' => true,
            'msg' => 'Riddles Added Successfully',
            'data' => $riddle,
        ], 200);
    }
}
