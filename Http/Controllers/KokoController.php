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
use Modules\Koko\Services\KokoService; // Added KokoService import

class KokoController extends Controller
{
    protected $transactionUtil;
    protected $moduleUtil;
    protected $kokoService; // Changed from businessUtil to kokoService

    /**
     * Constructor
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, KokoService $kokoService) // Changed parameters
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->kokoService = $kokoService; // New assignment
    }

    /**
     * Display Koko Settings
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'kokobnpl_module') || auth()->user()->can('business_settings.access'))) {
            abort(403, 'Unauthorized action.');
        }

        $business = Business::where('id', $business_id)->first();
        $koko_setting = KokoSetting::where('business_id', $business_id)->first();
        
        // If no settings exist, create a default one
        if (empty($koko_setting)) {
            $koko_setting = KokoSetting::create([
                'business_id' => $business_id,
                'mode' => 'sandbox'
            ]);
        }

        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = \App\Account::forDropdown($business_id, false, false);
        }

        // Get available custom payment types to show their current labels
        $custom_labels = $business->custom_labels;
        if (!is_array($custom_labels)) {
            $custom_labels = json_decode($custom_labels, true) ?? [];
        }

        $payment_methods = [];
        for ($i=1; $i<=7; $i++) {
            $key = 'custom_pay_' . $i;
            $current_label = $custom_labels['payments'][$key] ?? 'Custom Payment ' . $i;
            $payment_methods[$key] = 'Slot ' . $i . ' (Current Label: ' . $current_label . ')';
        }

        return view('koko::index')
            ->with(compact('koko_setting', 'payment_methods', 'accounts', 'custom_labels', 'business'));
    }

    /**
     * Update Koko Settings
     */
    public function updateSettings(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'kokobnpl_module') || auth()->user()->can('business_settings.access'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'merchant_id', 'api_key', 'public_key', 'private_key', 
                'mode', 'payment_method', 'account_id', 'pos_account_id',
                'fee_percentage', 'max_fee_amount'
            ]);

            // Do not overwrite sensitive keys if they are empty
            if (empty($input['api_key'])) {
                unset($input['api_key']);
            }
            if (empty($input['public_key'])) {
                unset($input['public_key']);
            }
            if (empty($input['private_key'])) {
                unset($input['private_key']);
            }

            $input['enable_fee'] = $request->has('enable_fee') ? 1 : 0;
            $input['fee_percentage'] = !empty($input['fee_percentage']) ? $this->transactionUtil->num_uf($input['fee_percentage']) : 0;
            $input['max_fee_amount'] = !empty($input['max_fee_amount']) ? $this->transactionUtil->num_uf($input['max_fee_amount']) : 0;

            KokoSetting::updateOrCreate(
                ['business_id' => $business_id],
                $input
            );

            // Auto-update Global Custom Label
            $payment_method = $request->input('payment_method');
            $payment_label = $request->input('payment_label');

            if (!empty($payment_method) && !empty($payment_label)) {
                $business = Business::find($business_id);
                $custom_labels = $business->custom_labels;
                if (!is_array($custom_labels)) {
                    $custom_labels = json_decode($custom_labels, true) ?? [];
                }
                $custom_labels['payments'][$payment_method] = $payment_label;
                $business->custom_labels = $custom_labels;
                $business->save();
            }

            $output = [
                'success' => true,
                'msg' => __('business.settings_updated_success')
            ];
        } catch (\Exception $e) {
            Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
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
            Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
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
        $signature = $request->input('signature');

        $dataString = $orderId . $trnId . $status . $desc;
        
        return $this->kokoService->validateSignature($dataString, $signature, $koko_setting->public_key);
    }

    /**
     * Check if payment already exists
     */
    protected function isPaymentExists($payment_id, $payment_method)
    {
        return TransactionPayment::where('transaction_no', $payment_id)
            ->where('method', $payment_method)
            ->exists();
    }

    /**
     * Process Koko Payment
     */
    protected function processPayment($transaction, $amount, $currency, $payment_id, $kokoSetting)
    {
        // DEBUG: Log incoming parameters
        Log::debug("Koko processPayment: transaction_id=" . $transaction->id . ", amount=" . $amount . ", payment_id=" . ($payment_id ?? 'NULL') . ", currency=" . $currency);
        
        // SECURITY: Final validation before creating payment
        if (empty($payment_id)) {
            Log::error("Koko processPayment: Rejecting payment with missing payment_id", ['transaction_id' => $transaction->id]);
            throw new \Exception("Invalid payment_id: payment creation rejected");
        }
        
        // Mock session user if needed (for webhook)
        $original_user_id = session('user.id');
        $business = Business::find($transaction->business_id);
        if ($business && $business->owner_id && empty($original_user_id)) {
            session(['user.id' => $business->owner_id]);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();
            $business_id = $transaction->business_id;
            
            // 1. Calculate and Add Convenience Fee if enabled
            $feeService = new KokoFeeService();
            $feeData = $feeService->calculateConvenienceFee($transaction->final_total, $kokoSetting);
            $convenience_fee = $feeData['convenience_fee'] ?? 0;
            
            if ($kokoSetting->enable_fee && $convenience_fee > 0) {
                // Add fee to invoice like PayHere
                if (empty($transaction->additional_expense_key_1) || $transaction->additional_expense_key_1 !== 'Koko Convenience Fee') {
                    $transaction->additional_expense_key_1 = 'Koko Convenience Fee';
                    $transaction->additional_expense_value_1 = $convenience_fee;
                    $transaction->final_total += $convenience_fee;
                    $transaction->save();
                    
                    Log::info("Koko Module: Convenience fee added to invoice", [
                        'transaction_id' => $transaction->id,
                        'fee' => $convenience_fee,
                        'new_total' => $transaction->final_total
                    ]);

                    // Refresh to get updated final_total
                    $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();
                }
            }

            $target_account_id = $kokoSetting->pos_account_id ?? $kokoSetting->account_id;
            $payment_method = $kokoSetting->payment_method ?? 'custom_pay_1';
            $created_by = session('user.id') ?? (auth()->id() ?? 1);

            // 2. Create Payment Entry
            $payment_data = [
                'transaction_id' => $transaction->id,
                'business_id' => $business_id,
                'amount' => $transaction->final_total, 
                'method' => $payment_method,
                'transaction_no' => $payment_id,
                'paid_on' => \Carbon\Carbon::now()->toDateTimeString(),
                'created_by' => $created_by,
                'payment_for' => $transaction->contact_id,
                'note' => 'Koko Payment Ref: ' . $payment_id . ($convenience_fee > 0 ? ' (Incl. Fee: ' . $convenience_fee . ')' : ''),
                'account_id' => $target_account_id
            ];

            $payment = TransactionPayment::create($payment_data);

            // 3. Update Transaction Payment Status
            $this->transactionUtil->updatePaymentStatus($transaction->id);

            DB::commit();
            Log::info("Koko Module: Payment Processed Success", [
                'order_id' => $transaction->invoice_no,
                'payment_amount' => $transaction->final_total,
                'payment_id' => $payment_id
            ]);
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Koko Module Processing Error: " . $e->getMessage());
            return false;
        } finally {
            // Restore original session state
            if ($original_user_id !== null) {
                session(['user.id' => $original_user_id]);
            } else {
                session()->forget('user.id');
            }
        }
    }
}
