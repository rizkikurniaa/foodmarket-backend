<?php

namespace App\Http\Controllers\API;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 6);
        $food_id = $request->input('food_id');
        $status = $request->input('status');

        if($id)
        {
            $transaction = Transaction::with(['food','user'])->find($id);

            if($transaction)
            {
                return ResponseFormatter::success(
                    $transaction,
                    'Successfully get transaction data'
                );
            }else{
                return ResponseFormatter::error(
                    null,
                    'Data transaction not found',
                    404
                );
            }
        }

        $transaction = Transaction::with(['food', 'user'])->where('user_id', Auth::user()->id);

        if($food_id)
        {
            $transaction->where('food_id', $food_id);
        }

        if($status)
        {
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Transaction data successfully retrieved'
        );
    }

    public function update(Request $request, $id)
    {
        //get data by id
        $transaction = Transaction::findOrFail($id);

        //then we update the data
        $transaction->update($request->all());

        //and then give the updated data as a return
        return ResponseFormatter::success($transaction, 'Transaction updated successfully');
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'food_id' => 'required|exists:food,id',
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required'
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => '', 
        ]);

        //Configure Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        //Call transaction that already created
        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        //Create Midtrans Transaction
        $midtrans = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => (int) $transaction->total
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => []
        ];
        //Call Midtrans
        try{
            //Get first page midtrans payment
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            //Return data to API
            return ResponseFormatter::success($transaction, 'Transaction Successful');
        }catch(Exception $e){
            return ResponseFormatter::error($e->getMessage(), 'Transaction Failed');
        }
    }
}
