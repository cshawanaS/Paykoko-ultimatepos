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
        $feeData = $paymentData['fee_data'] ?? [];
        $convenience_fee = $feeData['convenience_fee'] ?? 0;
    @endphp

    <div id="koko-payment-widget" class="payment-widget-item hidden-print" style="display: none;">
        <div class="koko-banner-inner">
            <div style="margin-bottom: 15px;">
                <a href="https://paykoko.com/" target="_blank" style="text-decoration: none; display: inline-block;">
                    <img src="https://paykoko.com/img/logo1.7ff549c0.png" alt="Koko" style="height: 28px; vertical-align: middle;">
                </a>
            </div>

            <div style="font-size: 13px; color: #555; margin-bottom: 12px; line-height: 1.4;">
                Pay 3 installments of <br>
                <span style="color: #333; font-weight: 700; font-size: 16px;">{{ $koko_currency }} {{ $installment_amount }}</span>
                @if($convenience_fee > 0)
                    <br><small style="font-size: 10px; color: #777;">(incl. processing fees)</small>
                @endif
            </div>

            <form action="{{ $paymentData['action_url'] }}" method="POST" id="koko_top_payment_form">
                @foreach($paymentData['fields'] as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                
                <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                    <button type="submit" style="background: #000; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <span style="font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">PAY NOW</span>
                    </button>
                    
                    <a href="https://paykoko.com/customer-education?Amount={{ $installment_amount }}" target="_blank" style="color: #999; text-decoration: none;">
                        <img src="https://koko-merchant.oss-ap-southeast-1.aliyuncs.com/bnpl-site-cms-dev/koko-images/info.png" alt="Info" style="height: 16px; opacity: 0.6;">
                    </a>
                </div>
            </form>
            
            <div style="margin-top: 12px; font-size: 10px; color: #888; text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em;">
                Interest-free • BNPL Partner
            </div>
        </div>
    </div>

    <style>
        .payment-widgets-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px auto 30px;
            width: 100%;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .payment-widget-item {
            flex: 1 1 320px;
            max-width: 100%;
        }
        .koko-banner-inner {
            padding: 20px;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            background-color: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border-top: 4px solid #ff3399;
            text-align: center;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        .koko-banner-inner:hover {
            transform: translateY(-2px);
        }
        #koko_top_payment_form button:hover {
            background: #333 !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        @media (max-width: 600px) {
            .payment-widget-item {
                flex: 1 1 100%;
            }
        }
    </style>

    <script>
        (function() {
            var containerId = 'payment-widgets-top-container';
            var container = document.getElementById(containerId);
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'payment-widgets-container hidden-print';
                var currentScript = document.currentScript || (function() {
                    var scripts = document.getElementsByTagName('script');
                    return scripts[scripts.length - 1];
                })();
                currentScript.parentNode.insertBefore(container, currentScript);
            }
            var widget = document.getElementById('koko-payment-widget');
            if (widget && container) {
                container.appendChild(widget);
                widget.style.display = 'block';
            }
        })();
    </script>
@endif
