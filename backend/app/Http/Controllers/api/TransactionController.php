<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\VCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Transaction::all();

    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        return new TransactionResource($transaction);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        /*
         * - recebe:
         * vcard, value, confirmation_code e payment_reference(vcard destino ou referencia)
         * posteriormente adiconou-se o type (C ou D) e payment_type (VCARD, IBAN, PAYPAL, VISA, MB, MBWAY)
         * - precisa de preencher:
         * vcard, date, datetime, type, value, old_balance, new_balamce, payment_type,
         * payment_reference, pair_transaction, pair_vcard, category_id, description (nao obrigatorio)
         *
         * no caso da referencia destino ser um vcard, cria uma outra transação ligada por
         * pair_transaction e pair_vcard (null em caso contrario)
         * sabemos que o destinatário é vcard se o parametro payment_type for VCARD
         */

        // FIRST VALIDATIONS

        // credit transactions are only handled by admins so they dont have balance nor do they have confirmation_codes
        // this makes so that only debit transations (the one made by users) validate the confirmation_code
        // and the balance of the user (adminds dont have balance)

        $sender = VCard::where('phone_number', $request->vcard)->first();

        if ($request->type != 'C') {
            // verify if confirmation code is the correct one of the user
            $vcardOrigin = VCard::where('phone_number', $request->vcard)->first();
            if (!password_verify($request->confirmation_code, $vcardOrigin->confirmation_code)) {
                $this->logDenied($request, 'invalid_confirmation_code');
                return response()->json(['message' => 'Invalid confirmation code'], 422);
            }

            // verify if sender has enough money on account balance
            if ($vcardOrigin->balance < $request->value) {
                $this->logDenied($request, 'insufficient_balance');
                return response()->json(['message' => 'Insuficient balance'], 422);
            }

            // verify if value being sent is higher than max_debit (invalid)
            if ($request->value > $vcardOrigin->max_debit) {
                $this->logDenied($request, 'max_debit_exceeded');
                return response()->json(['message' => 'Value higher than maximum debit allowed'], 422);
            }

            // verify if sender is not sending money to himself
            if ($request->vcard == $request->payment_reference) {
                $this->logDenied($request, 'self_transfer');
                return response()->json(['message' => 'You cannot send money to yourself'], 422);
            }
        }

        // verify if value being sent is at least 0.01€
        if ($request->value < 0.01) {
            $this->logDenied($request, 'amount_too_small');
            return response()->json(['message' => 'Minimum transfer amount is 0.01€'], 422);
        }
        
        // VCARD
        if ($request->payment_type == 'VCARD') {
            // Verify if destination vcard exists or is blocked
            $destinVCard = VCard::where('phone_number', $request->payment_reference)->first();
            if (!$destinVCard || $destinVCard->blocked == 1) {
                $this->logDenied($request, 'receiver_invalid_or_blocked');
                return response()->json(['message' => $request->payment_reference . ' does not exist or is blocked'], 404);
            }

            try {

                DB::transaction(function () use ($request, $sender, $destinVCard) {

                    $date = date('Y-m-d');
                    $datetime = date('Y-m-d H:i:s');

                    // Money sending transaction
                    if ($request->type != 'C') {
                        $transaction1 = new Transaction();
                        $transaction1->vcard = $request->vcard;
                        $transaction1->date = $date;
                        $transaction1->datetime = $datetime;
                        $transaction1->type = 'D'; // como o utilizador está a enviar dinheiro, a primeira operação é sempre Debito
                        $transaction1->value = $request->value;
                        $vcardBalance = $sender->balance;
                        $transaction1->old_balance = $vcardBalance;
                        $transaction1->new_balance = $vcardBalance - $request->value;
                        $transaction1->payment_type = $request->payment_type;
                        $transaction1->payment_reference = $request->payment_reference;
                        $transaction1->pair_vcard = $request->payment_reference;
                        $transaction1->category_id = $request->category_id;
                        $transaction1->description = $request->description;
                    }


                    // Money reception transaction
                    $transaction2 = new Transaction();
                    $transaction2->vcard = $request->payment_reference;
                    $transaction2->date = $date;
                    $transaction2->datetime = $datetime;
                    $transaction2->type = 'C'; // tendo em conta esta operaçao ser a inversa, esta será de Crédito
                    $transaction2->value = $request->value;
                    $payment_referenceBalance = $destinVCard->balance;
                    $transaction2->old_balance = $payment_referenceBalance;
                    $transaction2->new_balance = $payment_referenceBalance + $request->value;
                    $transaction2->payment_type = $request->payment_type;
                    $transaction2->payment_reference = $request->vcard;
                    $transaction2->pair_transaction = ($request->type != 'C') ? $transaction1->id : null;
                    $transaction2->pair_vcard = ($request->type != 'C') ? $request->vcard : null;
                    $transaction2->category_id = null;
                    $transaction2->description = null;


                    if ($request->type != 'C') {
                        // Save transactions to get their id's
                        $transaction1->save();
                        $transaction2->save();

                        // Update pair_transaction properties
                        $transaction1->pair_transaction = $transaction2->id;
                        $transaction2->pair_transaction = $transaction1->id;

                        // Save transactions again to update pair_transaction values
                        $transaction1->save();
                        $transaction2->save();

                        // Update both individual's balances
                        $sender->update(['balance' => $transaction1->new_balance]);
                        $destinVCard->update(['balance' => $transaction2->new_balance]);
                    } else {
                        $transaction2->save();
                        $destinVCard->update(['balance' => $transaction2->new_balance]);
                    }

                     Log::channel('soc')->info('transaction.created', [
                        'event_type' => 'transaction.created',
                        'transaction_id' => $transaction1?->id ?? $transaction2->id,
                        'user_id' => auth()->id(),
                        'amount' => $request->value,
                        'currency' => 'EUR',
                        'from_card' => $this->mask($sender->phone_number),
                        'to_card' => $this->mask($destinVCard->phone_number),
                        'payment_type' => 'VCARD',
                        'ip' => request()->ip(),
                        'timestamp' => now()->toIso8601String()
                    ]);
                });
                
            } catch (\Exception $e) {
                $this->logFailed($request, 'db_error');
                return response()->json([
                    'message' => 'Error creating transaction',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // ANY OTHER PAYMENT TYPE
        elseif (in_array($request->payment_type, ['IBAN', 'PAYPAL', 'VISA', 'MB', 'MBWAY'])) {

            $endpoint = ($request->type == 'D') ? 'credit' : 'debit';

            // API call
            $response = Http::post("https://dad-202324-payments-api.vercel.app/api/{$endpoint}", [
                'type' => $request->payment_type,
                'reference' => $request->payment_reference,
                'value' => (float) $request->value,
            ]);

            // Check the response and handle accordingly
            if (!$response->successful()) {
                $this->logFailed($request, 'external_api_failure');

                $responseData = $response->json(); // Parse JSON response
                $message = isset($responseData['message']) ? $responseData['message'] : '(api) Error sending transaction';
                return response()->json(['message' => $message], $response->status());
            }
            
            try {
                DB::transaction(function () use ($request, $sender) {
                    // Money sending transaction
                    $transaction = new Transaction();

                    $transaction->vcard = $request->vcard;
                    $transaction->date = date('Y-m-d');
                    $transaction->datetime = date('Y-m-d H:i:s');
                    $transaction->type = $request->type;
                    $transaction->value = $request->value;
                    $vcardBalance = $sender->balance;
                    $transaction->old_balance = $vcardBalance;
                    $transaction->new_balance = ($request->type == 'D') ? $vcardBalance - $request->value : $vcardBalance + $request->value;
                    $transaction->payment_type = $request->payment_type;
                    $transaction->payment_reference = $request->payment_reference;
                    $transaction->pair_vcard = null;
                    $transaction->pair_transaction = null;
                    $transaction->category_id = $request->category_id;
                    $transaction->description = $request->description;

                    $transaction->save();

                    // give the user 1 spin for every 10 euros sent if it wasn't a credit transaction made by the admin
                    if ($request->type != 'C') {
                        $sender->spins += floor($request->value / 10);
                        $sender->update(['spins' => $sender->spins, 'balance' => $transaction->new_balance]);
                    }

                    $sender->update(['balance' => $transaction->new_balance]);

                    Log::channel('soc')->info('transaction.created', [
                        'event_type' => 'transaction.created',
                        'transaction_id' => $transaction->id,
                        'user_id' => auth()->id(),
                        'amount' => $request->value,
                        'currency' => 'EUR',
                        'from_card' => $this->mask($sender->phone_number),
                        'to_card' => $this->mask($request->payment_reference),
                        'payment_type' => $request->payment_type,
                        'external' => true,
                        'ip' => request()->ip(),
                        'timestamp' => now()->toIso8601String()
                    ]);

                });

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Error creating transaction',
                    'error' => $e->getMessage()
                ], 500);
            }

        } else {
            return response()->json(['message' => 'Invalid payment type'], 401);
        }

        if ($request->type != 'C')
            return response()->json(['message' => "{$request->value}€ sent to {$request->payment_reference} successfully", "spins" => floor($request->value / 10)], 200);

        if ($request->type == 'C')
            return response()->json(['message' => "{$request->value}€ sent to {$request->vcard} successfully"], 200);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $transaction = Transaction::where('id', $request->id)->first();

        $transaction->description = $request->description;

        $transaction->category_id = $request->category_id;

        $transaction->save();

        // Return the updated transaction
        return new TransactionResource($transaction);
    }

    public function getTransactionsSumBetweenDates(Request $request)
    {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $sumBetweenDates = Transaction::whereBetween('date', [$startDate, $endDate])->sum('value');
        $countBetweenDates = Transaction::whereBetween('date', [$startDate, $endDate])->count();

        return response()->json(['sumBetweenDates' => $sumBetweenDates, 'countBetweenDates' => $countBetweenDates]);
    }

    public function getOlderTransaction(Request $request)
    {

        $olderTransaction = Transaction::orderBy('date')->first();

        return response()->json(['olderTransaction' => $olderTransaction]);
    }

    public function getTransactionsCountByType(Request $request)
    {
        $paymentType = $request->input('paymentType');

        $countByPayementType = Transaction::where('payment_type', $paymentType)->count();
        return response()->json(['countByPayementType' => $countByPayementType]);
    }

    public function getTransactionStatistics()
    {
        $transactionsSum = Transaction::sum('value');
        $transactionsCount = Transaction::count();

        $transactionsSumByMonth = Transaction::selectRaw('MONTH(date) as month, YEAR(date) as year, SUM(value) as sum')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $transactionsCountByMonth = Transaction::selectRaw('MONTH(date) as month, YEAR(date) as year, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $transactionByPaymentMethod = Transaction::selectRaw('payment_type, COUNT(*) as transaction_count')
            ->groupBy('payment_type')
            ->get();

        $averageTransactionAmounts = Transaction::selectRaw('MONTH(date) as month, YEAR(date) as year, AVG(value) as average_amount')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $paymentMethods = $transactionByPaymentMethod->pluck('payment_type')->toArray();
        $transactionCounts = $transactionByPaymentMethod->pluck('transaction_count')->toArray();

        return response()->json([
            'transactionsSum' => $transactionsSum,
            'transactionsCount' => $transactionsCount,
            'transactionsSumByMonth' => $transactionsSumByMonth,
            'transactionsCountByMonth' => $transactionsCountByMonth,
            'paymentMethods' => $paymentMethods,
            'transactionCounts' => $transactionCounts,
            'averageTransactionAmounts' => $averageTransactionAmounts,
        ]);
    }

    // ---------------- SOC HELPERS ----------------

    private function logDenied($request, $reason)
    {
        Log::channel('soc')->warning('transaction.denied', [
            'event_type' => 'transaction.denied',
            'user_id' => auth()->id(),
            'reason' => $reason,
            'amount' => $request->value,
            'from_card' => $this->mask($request->vcard),
            'to_card' => $this->mask($request->payment_reference),
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    private function logFailed($request, $reason)
    {
        Log::channel('soc')->error('transaction.failed', [
            'event_type' => 'transaction.failed',
            'user_id' => auth()->id(),
            'reason' => $reason,
            'amount' => $request->value,
            'from_card' => $this->mask($request->vcard),
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    private function mask($v)
    {
        return '********' . substr($v, -4);
    }
}
