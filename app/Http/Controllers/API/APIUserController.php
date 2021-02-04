<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseFormatter;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class APIUserController extends Controller
{
    public function login(Request $request){
        try {
            //Input validation
            $request->validate([
                'email' => 'email|required',
                'password' => 'required',
            ]);

            //Check credentials (login)
            $credentials = request(['email', 'password']);
            if(!Auth::attempt([$credentials])){
                return ResponseFormatter::error([
                    'message' => 'Unauthorized'
                ], 'Authentication Failed', 500);
            }

            //If hash not match then give an error
            $user = User::where('email', $request->email)->first();
            if(!Hash::check($request->password, $user->password, [])){
                throw new \Exception('Invalid  Credentials'); 
            }

            //If succeed then login
            $tokenResult = $user->createToken('authToken')->plaintTextToken;
            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Authenticated');

        } catch(Exception $error){
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication Failed', 500);
        }
    }
}
