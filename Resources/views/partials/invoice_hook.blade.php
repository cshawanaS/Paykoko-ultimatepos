@php
    $token = request()->route('token');
    $transaction = \App\Transaction::where('invoice_token', $token)->with(['business', 'contact', 'business.currency'])->first();
    
    // If not a token route, try to get from view data if available (internal view)
    if (empty($transaction) && !empty($transaction_id)) {
        $transaction = \App\Transaction::with(['business', 'contact', 'business.currency'])->find($transaction_id);
    }

    if (!empty($transaction)) {
        $kokoService = new \Modules\Koko\Services\KokoService();
        $paymentData = $kokoService->getPaymentData($transaction);
    }
@endphp

@if(!empty($transaction) && !empty($paymentData) && !isset($paymentData['error']) && $transaction->payment_status != 'paid')
    @php
        $koko_currency = $paymentData['currency'];
        $feeData = $paymentData['fee_data'];
        $total_payable = $feeData['total_payable'];
        $convenience_fee = $feeData['convenience_fee'];
        $total_with_fee = $feeData['total_with_fee'];
        $fee_display_percent = $feeData['fee_display_percent'];
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

            <form action="{{ $paymentData['action_url'] }}" method="POST" id="koko_payment_form">
                @foreach($paymentData['fields'] as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                
                <button type="submit" style="border: none; background: none; padding: 0; cursor: pointer;">
                    <img src="{{ asset('modules/koko/img/daraz-koko.png') }}" alt="Pay with Koko" style="width: 100%; max-width: 200px;">
                </button>
            </form>
            
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <i class="fa fa-lock"></i> Secure 3-part installment payments
            </p>
        </div>
    </div>
@endif
