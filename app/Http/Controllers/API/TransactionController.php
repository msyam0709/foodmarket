<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
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

        if ($id) {
            $transaction = Transaction::with(['food', 'user']);

            if ($transaction) {
                return ResponseFormatter::success(
                    $transaction,
                    'Data transaksi berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data transaksi tidak ada',
                    404
                );
            }
        }

        $transaction = Transaction::with(['food', 'user'])->where('user_id', Auth::user()->id);

        if ($food_id) {
            $transaction->where('food_id', $food_id);
        }

        if ($status) {
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success([
            $transaction->paginate($limit),
            'Data list transaksi berhasil diambil'
        ]);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFails($id);

        $transaction->update($request->all());
        return ResponseFormatter::success($transaction, 'Transaksi berhasil diperbarui');
    }

    public function checkout(Request $request)
    {
        //Validasi Request
        $request->validate([
            'food_id'  => 'required|exists:food,id',
            'user_id'  => 'required|exists:users,id',
            'quantity' => 'required',
            'total'    => 'required',
            'status'   => 'required',
        ]);

        //Save transaksi
        $transaction = Transaction::create([
            'food_id'     => $request->food_id,
            'user_id'     => $request->user_id,
            'quantity'    => $request->quantity,
            'total'       => $request->total,
            'paymetn_url' => ''
        ]);

        //Konfigurasikan Midtrans
        Config::$serverKey    = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized  = config('services.midtrans.isSanitized');
        Config::$is3ds        = config('services.midtrans.is3ds');

        //Aambil Transaksi yang sudah disimpan
        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        //Setup transaksi midtrans
        $midtrans = [
            'transactions_details' => [
                'order_id'     => $transaction->id,
                'gross_amount' => $transaction->total,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email'      => $transaction->user->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb'            => [],
        ];

        try {
            $payment_url = Snap::createTransaction($midtrans)->redirect_url;
            $transaction->payment_url = $payment_url;
            $transaction->save();

            return ResponseFormatter::success($transaction, 'Transaksi Berhasil');
        } catch (Exception $error) {
            return ResponseFormatter::error($error->getMessage(), 'Transaksi Gagal');
        }
    }
}
