@php
    $token = request()->route('token');
    $transaction = \App\Transaction::where('invoice_token', $token)->with(['business', 'contact', 'business.currency'])->first();
    
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
        $installment_amount = $paymentData['installment_amount'];
        $feeData = $paymentData['fee_data'];
        $convenience_fee = $feeData['convenience_fee'];
    @endphp

    <div class="row">
        <div class="col-md-12 text-center hidden-print" style="margin-top: 20px;">
            <div style="padding: 15px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #ffffff; max-width: 450px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                
                <div style="font-size: 16px; color: #555; margin-bottom: 15px; font-weight: 500;">
                    Pay in 3 installments of <span style="color: #333; font-weight: 700;">{{ $koko_currency }} {{ $installment_amount }}</span> with
                </div>

                <form action="{{ $paymentData['action_url'] }}" method="POST" id="koko_guest_payment_form">
                    @foreach($paymentData['fields'] as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    
                    <button type="submit" style="border: none; background: none; padding: 0; cursor: pointer; display: inline-block; vertical-align: middle;">
                        <img src="https://paykoko.com/img/logo1.7ff549c0.png" alt="Koko" style="height: 24px; vertical-align: middle;">
                    </button>
                    
                    <a href="https://paykoko.com/customer-education?Amount={{ $installment_amount }}" target="_blank" style="display: inline-block; vertical-align: middle; margin-left: 5px;">
                        <img src="https://koko-merchant.oss-ap-southeast-1.aliyuncs.com/bnpl-site-cms-dev/koko-images/info.png" alt="Info" style="height: 15px; vertical-align: middle;">
                    </a>
                </form>
                
                <p style="margin-top: 15px; font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">
                    Secure interest-free payments @if($convenience_fee > 0) <br>(Incl. processing fees) @endif
                </p>
            </div>
        </div>
    </div>
@endif
