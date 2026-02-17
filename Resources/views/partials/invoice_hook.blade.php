@php
    $token = request()->route('token');
    $transaction = \App\Transaction::where('invoice_token', $token)->with(['business', 'contact'])->first();
    
    // If not a token route, try to get from view data if available (internal view)
    if (empty($transaction) && !empty($transaction_id)) {
        $transaction = \App\Transaction::with(['business', 'contact'])->find($transaction_id);
    }

    if (!empty($transaction)) {
        $koko_setting = \Modules\Koko\Entities\KokoSetting::where('business_id', $transaction->business_id)->first();
    }
@endphp

@if(!empty($transaction) && !empty($koko_setting) && !empty($koko_setting->merchant_id) && $transaction->payment_status != 'paid')
    @php
        $business_util = new \App\Utils\BusinessUtil();
        $business_details = $business_util->getDetails($transaction->business_id);
        
        $koko_currency = $business_details->currency_code;
        
        // Calculate remaining balance for partial payments
        $paid_amount = \App\TransactionPayment::where('transaction_id', $transaction->id)->sum('amount');
        $remaining_balance = $transaction->final_total - $paid_amount;
        
        // Use fee service
        $feeService = new \Modules\Koko\Services\KokoFeeService();
        $feeData = $feeService->calculateConvenienceFee($remaining_balance, $koko_setting);
        
        $total_payable = $feeData['total_payable'];
        $convenience_fee = $feeData['convenience_fee'];
        $total_with_fee = $feeData['total_with_fee'];
        $fee_display_percent = $feeData['fee_display_percent'];
        $koko_amount = $feeData['koko_amount'];
        
        $koko_order_id = $transaction->id;
        $koko_mode = $koko_setting->mode ?? 'sandbox';
        $koko_url = ($koko_mode == 'live') ? 'https://prodapi.paykoko.com/api/merchants/orderCreate' : 'https://devapi.paykoko.com/api/merchants/orderCreate';
        
        // Generate Signature
        // Data string format: merchant + amount + currency + pluginName + pluginVersion + returnUrl + responseUrl + orderId + reference + firstName + lastName + email + productName + apiKey + cancelUrl
        
        $pluginName = "ultimatepos";
        $pluginVersion = "1.0";
        $returnUrl = route('koko.return', ['id' => $transaction->id]);
        $responseUrl = route('koko.notify');
        $cancelUrl = $returnUrl;
        $reference = $koko_setting->merchant_id . rand(111, 999) . '-' . $transaction->invoice_no;
        $firstName = $transaction->contact->first_name;
        $lastName = $transaction->contact->last_name ?? '';
        $email = $transaction->contact->email ?? '';
        $productName = "Invoice " . $transaction->invoice_no;
        $apiKey = $koko_setting->api_key;
        
        $dataString = $koko_setting->merchant_id . $koko_amount . $koko_currency . $pluginName . $pluginVersion . 
                      $returnUrl . $responseUrl . $koko_order_id . $reference . $firstName . 
                      $lastName . $email . $productName . $apiKey . $cancelUrl;

        $privateKey = $koko_setting->private_key;
        $pkeyid = openssl_get_privatekey($privateKey);
        $signatureEncoded = "";
        if ($pkeyid) {
            openssl_sign($dataString, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
            $signatureEncoded = base64_encode($signature);
            openssl_free_key($pkeyid);
        }
        
    @endphp

    <div class="row">
        <div class="col-md-12 text-center hidden-print" style="margin-top: 20px;">
            <h4 style="margin-bottom: 10px;">Pay in 3 installments with</h4>
            
            @if($convenience_fee > 0)
            <div style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border-radius: 5px; max-width: 400px; margin: 0 auto 15px; border: 1px solid #ddd;">
                <table style="width: 100%; text-align: left; font-size: 14px;">
                    <tr>
                        <td style="padding: 5px 0;">Invoice Amount:</td>
                        <td style="text-align: right; padding: 5px 0;"><strong>{{ $koko_currency }} {{ number_format($total_payable, 2) }}</strong></td>
                    </tr>
                    <tr style="color: #666;">
                        <td style="padding: 5px 0;">Koko Handling Fee ({{ $fee_display_percent }}%):</td>
                        <td style="text-align: right; padding: 5px 0;">{{ $koko_currency }} {{ number_format($convenience_fee, 2) }}</td>
                    </tr>
                    <tr style="border-top: 2px solid #333; font-weight: bold; font-size: 16px;">
                        <td style="padding: 10px 0 5px 0;">Total to Pay:</td>
                        <td style="text-align: right; padding: 10px 0 5px 0; color: #28a745;">{{ $koko_currency }} {{ number_format($total_with_fee, 2) }}</td>
                    </tr>
                </table>
            </div>
            @endif

            <form action="{{ $koko_url }}" method="POST" id="koko_payment_form">
                <input type="hidden" name="_mId" value="{{ $koko_setting->merchant_id }}">
                <input type="hidden" name="api_key" value="{{ $koko_setting->api_key }}">
                <input type="hidden" name="_returnUrl" value="{{ $returnUrl }}">
                <input type="hidden" name="_responseUrl" value="{{ $responseUrl }}">
                <input type="hidden" name="_cancelUrl" value="{{ $cancelUrl }}">
                <input type="hidden" name="_currency" value="{{ $koko_currency }}">
                <input type="hidden" name="_amount" value="{{ $koko_amount }}">
                <input type="hidden" name="_reference" value="{{ $reference }}">
                <input type="hidden" name="_pluginName" value="{{ $pluginName }}">
                <input type="hidden" name="_pluginVersion" value="{{ $pluginVersion }}">
                <input type="hidden" name="_orderId" value="{{ $koko_order_id }}">
                <input type="hidden" name="_firstName" value="{{ $firstName }}">
                <input type="hidden" name="_lastName" value="{{ $lastName }}">
                <input type="hidden" name="_email" value="{{ $email }}">
                <input type="hidden" name="_description" value="{{ $productName }}">
                <input type="hidden" name="_mobileNo" value="{{ $transaction->contact->mobile }}">
                <input type="hidden" name="dataString" value="{{ $dataString }}">
                <input type="hidden" name="signature" value="{{ $signatureEncoded }}">
                
                <button type="submit" style="border: none; background: none; padding: 0; cursor: pointer;">
                    <img src="https://devapi.paykoko.com/assets/img/daraz-koko.png" alt="Pay with Koko" style="width: 100%; max-width: 200px;">
                </button>
            </form>
            
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <i class="fa fa-lock"></i> Secure 3-part installment payments
            </p>
        </div>
    </div>
@endif
