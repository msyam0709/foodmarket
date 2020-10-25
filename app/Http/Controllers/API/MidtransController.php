<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransController extends Controller
{
    public function callback()
    {
        // Set konfigurasi mistrans
        Config::$serverKey    = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized  = config('services.midtrans.isSanitized');
        Config::$is3ds        = config('services.midtrans.is3ds');

        // Buat Instance midtrans notification
        $notificaiton = new Notification();

        // Assign ke variable untuk memudahkan coding
        $status = $notificaiton->transaction_status;
        $type = $notificaiton->payment_type;
        $fraud = $notificaiton->fraud_status;
        $order_id = $notificaiton->order_id;

        // Cari transaksi berdasarkan ID
        $transaction = Transaction::findOrFail($order_id);

        // Hadle notifikasi status midtrans
        if ($status == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    $transaction->status = 'PENDING';
                } else {
                    $transaction->status = 'SUCCES';
                }
            }
        } else if ($status == 'settlement') {
            $transaction->status = 'SUCCES';
        } else if ($status == 'pending') {
            $transaction->status = 'PENDING';
        } else if ($status == 'deny') {
            $transaction->status = 'CANCELED';
        } else if ($status == 'expire') {
            $transaction->status = 'CANCELED';
        }

        // Simpan transaksi
        $transaction->save();
    }
    public function success()
    {
        return view('midtrans.success');
    }
    public function unfinish()
    {
        return view('midtrans.unfinish');
    }
    public function error()
    {
        return view('midtrans.error');
    }
}
