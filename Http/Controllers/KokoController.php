<?php

namespace Modules\Koko\Http\Controllers;

use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use App\Business;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use App\Utils\ModuleUtil;
use App\Utils\BusinessUtil;
use Modules\Koko\Entities\KokoSetting;
use Modules\Koko\Services\KokoFeeService;

class KokoController extends Controller
{
    protected $transactionUtil;
    protected $moduleUtil;
    protected $businessUtil;

    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, BusinessUtil $businessUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessUtil = $businessUtil;
    }

    /**
     * Display Koko Settings
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'kokobnpl_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $koko_setting = KokoSetting::where('business_id', $business_id)->first();
        
        // If no settings exist, create a default one
        if (empty($koko_setting)) {
            $koko_setting = KokoSetting::create([
                'business_id' => $business_id,
                'mode' => 'sandbox'
            ]);
        }

        $payment_methods = $this->transactionUtil->payment_types(null, true, $business_id);
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('Account')) {
            $accounts = \App\Account::forDropdown($business_id, true, false);
        }

        return view('koko::index')
            ->with(compact('koko_setting', 'payment_methods', 'accounts'));
    }

    /**
     * Update Koko Settings
     */
    public function updateSettings(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'kokobnpl_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'merchant_id', 'api_key', 'public_key', 'private_key', 
                'mode', 'payment_method', 'account_id', 'pos_account_id',
                'fee_percentage', 'max_fee_amount'
            ]);

            $input['enable_fee'] = $request->has('enable_fee') ? 1 : 0;
            $input['fee_percentage'] = !empty($input['fee_percentage']) ? $this->transactionUtil->num_uf($input['fee_percentage']) : 0;
            $input['max_fee_amount'] = !empty($input['max_fee_amount']) ? $this->transactionUtil->num_uf($input['max_fee_amount']) : 0;

            KokoSetting::updateOrCreate(
                ['business_id' => $business_id],
                $input
            );

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success')
            ];
        } catch (\Exception $e) {
            Log::error('Koko Settings Update Error: ' . $e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Koko Webhook Notify URL
     */
    public function notify(Request $request)
    {
        Log::info('Koko Webhook Received: ' . json_encode($request->all()));

        try {
            $order_id = $request->input('orderId');
            $trn_id = $request->input('trnId');
            $status = $request->input('status');
            $desc = $request->input('desc');

            if (empty($order_id)) {
                Log::error('Koko Webhook Error: Order ID missing');
                return response('Order ID missing', 400);
            }

            // Load transaction and setting
            $transaction = Transaction::find($order_id);
            if (empty($transaction)) {
                Log::error('Koko Webhook Error: Transaction not found for Order ID: ' . $order_id);
                return response('Transaction not found', 404);
            }

            $koko_setting = KokoSetting::where('business_id', $transaction->business_id)->first();
            if (empty($koko_setting)) {
                Log::error('Koko Webhook Error: Settings not found for business ID: ' . $transaction->business_id);
                return response('Settings not found', 404);
            }

            // Validate Signature
            if (!$this->validateRequest($request, $koko_setting)) {
                Log::error('Koko Webhook Error: Signature validation failed');
                return response('Signature validation failed', 400);
            }

            // Check if payment already recorded
            if ($this->isPaymentExists($trn_id, $koko_setting->payment_method)) {
                Log::info('Koko Webhook: Payment already exists for Trn ID: ' . $trn_id);
                return response('Payment already exists', 200);
            }

            if ($status === 'SUCCESS') {
                $amount = $transaction->final_total; // This should be handled carefully if convenience fee was added
                
                // If it was a frontend success, it might have already been processed
                // But normally we trust the webhook for backend processing
                
                $this->processPayment($transaction, $amount, 'LKR', $trn_id, $koko_setting);
                
                return response('Success', 200);
            } else {
                Log::warning('Koko Webhook: Payment status is ' . $status . ' for Trn ID: ' . $trn_id);
                return response('Payment status: ' . $status, 200);
            }

        } catch (\Exception $e) {
            Log::error('Koko Webhook Exception: ' . $e->getMessage());
            return response('Error', 500);
        }
    }

    /**
     * Koko Return URL
     */
    public function paymentReturn(Request $request, $id = null)
    {
        Log::info('Koko Return Page Loaded: ' . json_encode($request->all()));

        $transaction = Transaction::find($id);
        if (empty($transaction)) {
            return redirect()->to('/pos')->with('status', [
                'success' => false,
                'msg' => 'Transaction not found'
            ]);
        }

        $status = $request->input('status');
        $trn_id = $request->input('trnId');
        $frontend = filter_var($request->input('frontend'), FILTER_VALIDATE_BOOLEAN);

        if ($status === 'SUCCESS') {
            // Frontend successes are often redirects after the backend has already processed the webhook
            // but we can check if payment exists here too for robustness
            
            $output = [
                'success' => true,
                'msg' => 'Payment successful for Order ID: ' . ($request->input('orderId') ?? $transaction->invoice_no)
            ];
            
            // Redirect to invoice view or success page
            return redirect()
                ->route('view_invoice', ['token' => $transaction->invoice_token])
                ->with('status', $output);
        } else {
            $output = [
                'success' => false,
                'msg' => 'Payment failed or cancelled. Status: ' . $status
            ];
            
            return redirect()
                ->route('view_invoice', ['token' => $transaction->invoice_token])
                ->with('status', $output);
        }
    }

    /**
     * Validate Signature
     */
    protected function validateRequest(Request $request, $koko_setting)
    {
        $orderId = $request->input('orderId');
        $trnId = $request->input('trnId');
        $status = $request->input('status');
        $desc = $request->input('desc');
        $signature = base64_decode($request->input('signature'));

        $dataString = $orderId . $trnId . $status . $desc;
        
        $publicKey = $koko_setting->public_key;
        
        $pubKeyid = openssl_get_publickey($publicKey);
        if (!$pubKeyid) {
            Log::error('Koko Validation Error: Invalid public key');
            return false;
        }
        
        $signatureVerify = openssl_verify($dataString, $signature, $pubKeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKeyid);

        return $signatureVerify === 1;
    }

    /**
     * Check if payment already exists
     */
    protected function isPaymentExists($payment_id, $payment_method)
    {
        return TransactionPayment::where('note', 'LIKE', '%' . $payment_id . '%')
            ->where('method', $payment_method)
            ->exists();
    }

    /**
     * Process Koko Payment
     */
    protected function processPayment($transaction, $amount, $currency, $payment_id, $kokoSetting)
    {
        try {
            DB::beginTransaction();

            $business_id = $transaction->business_id;
            
            // 1. Calculate and Add Convenience Fee if enabled
            // We need to check if the payment includes a fee
            // For now, let's assume we record the amount received
            
            // In PayHere implementation, we added the fee to the invoice first
            $feeService = new KokoFeeService();
            $feeData = $feeService->calculateConvenienceFee($transaction->final_total, $kokoSetting);
            
            if ($kokoSetting->enable_fee && $feeData['convenience_fee'] > 0) {
                // Add fee to invoice
                $transaction->final_total += $feeData['convenience_fee'];
                $transaction->save();
                
                // Log the fee addition
                Log::info('Koko: Added convenience fee ' . $feeData['convenience_fee'] . ' to invoice ' . $transaction->invoice_no);
            }

            // 2. Create Payment Entry
            $payment_data = [
                'transaction_id' => $transaction->id,
                'business_id' => $business_id,
                'amount' => $transaction->final_total, // Record full amount including fee
                'method' => $kokoSetting->payment_method ?? 'custom_pay_1',
                'paid_on' => \Carbon\Carbon::now()->toDateTimeString(),
                'created_by' => 1, // System/Admin
                'payment_for' => $transaction->contact_id,
                'note' => 'Koko Payment ID: ' . $payment_id,
                'account_id' => $kokoSetting->account_id
            ];

            $payment = TransactionPayment::create($payment_data);

            // 3. Update Transaction Payment Status
            $this->transactionUtil->updatePaymentStatus($transaction->id);

            DB::commit();
            Log::info('Koko: Payment processed successfully for invoice ' . $transaction->invoice_no . ' with Trn ID: ' . $payment_id);
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Koko Payment Processing Error: ' . $e->getMessage());
            return false;
        }
    }
}
