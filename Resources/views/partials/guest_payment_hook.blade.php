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

    <div id="koko-guest-payment-widget" class="row">
        <div class="col-md-12 text-center hidden-print" style="margin-top: 20px;">
            <div style="padding: 15px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #ffffff; max-width: 450px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #ff3399;">
                
                <div style="font-size: 16px; color: #555; margin-bottom: 15px; font-weight: 500;">
                    Pay in 3 installments of <span style="color: #333; font-weight: 700;">{{ $koko_currency }} {{ $installment_amount }}</span> with
                </div>

                <form action="{{ $paymentData['action_url'] }}" method="POST" id="koko_guest_payment_form" target="koko_popup">
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

    <!-- Koko Loading Overlay (Guest) -->
    <div id="koko_guest_loading_overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); z-index: 10000; justify-content: center; align-items: center; font-family: 'Inter', sans-serif;">
        <div class="text-center" style="max-width: 400px; padding: 40px; border-radius: 20px; background: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <div id="koko_guest_overlay_icon">
                <i class="fa fa-refresh fa-spin fa-3x fa-fw" style="color: #ff3399;"></i>
            </div>
            <h3 id="koko_guest_overlay_title" style="color: #333; margin-top: 25px; font-weight: 700; font-size: 24px;">{{ __('koko::lang.connecting_to_koko') }}</h3>
            <p id="koko_guest_overlay_msg" style="color: #666; font-size: 16px; margin-top: 10px;">{{ __('koko::lang.please_wait') }}</p>
            
            <div id="koko_guest_overlay_timer" style="display: none; margin-top: 20px; font-weight: 700; color: #ff3399; font-size: 18px;">
                <span id="koko_guest_timer_seconds">5</span>s ...
            </div>

            <div id="koko_guest_overlay_footer" style="display: none; margin-top: 30px;">
                <button type="button" onclick="document.getElementById('koko_guest_loading_overlay').style.display='none'" class="btn btn-default" style="border-radius: 8px; padding: 10px 30px;">
                    {{ __('koko::lang.close') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var kokoPopup = null;
            var popupCheckInterval = null;

            var form = document.getElementById('koko_guest_payment_form');
            if (form) {
                form.onsubmit = function(e) {
                    var w = 500, h = 700;
                    var left = (window.innerWidth / 2) - (w / 2) + window.screenX;
                    var top = (window.innerHeight / 2) - (h / 2) + window.screenY;
                    
                    kokoPopup = window.open('', 'koko_popup', 'width='+w+',height='+h+',top='+top+',left='+left+',status=no,resizable=yes,scrollbars=yes');
                    document.getElementById('koko_guest_loading_overlay').style.display = 'flex';
                    
                    // Reset overlay state
                    document.getElementById('koko_guest_overlay_icon').innerHTML = '<i class="fa fa-refresh fa-spin fa-3x fa-fw" style="color: #ff3399;"></i>';
                    document.getElementById('koko_guest_overlay_title').innerText = "{{ __('koko::lang.connecting_to_koko') }}";
                    document.getElementById('koko_guest_overlay_msg').innerText = "{{ __('koko::lang.payment_in_progress') }}";
                    document.getElementById('koko_guest_overlay_footer').style.display = 'none';
                    document.getElementById('koko_guest_overlay_timer').style.display = 'none';

                    // Monitor closure
                    if (popupCheckInterval) clearInterval(popupCheckInterval);
                    popupCheckInterval = setInterval(function() {
                        if (kokoPopup && kokoPopup.closed) {
                            clearInterval(popupCheckInterval);
                            var footer = document.getElementById('koko_guest_overlay_footer');
                            if (footer && footer.style.display === 'none') {
                                document.getElementById('koko_guest_loading_overlay').style.display = 'none';
                            }
                        }
                    }, 1000);
                };
            }

            // Listen for window messages from the popup
            window.addEventListener('message', function(event) {
                if (event.origin !== window.location.origin) return;

                if (event.data && event.data.type === 'KOKO_PAYMENT_STATUS') {
                    if (popupCheckInterval) clearInterval(popupCheckInterval);
                    handleKokoStatus(event.data);
                }
            });

            function handleKokoStatus(data) {
                var overlay = document.getElementById('koko_guest_loading_overlay');
                var title = document.getElementById('koko_guest_overlay_title');
                var msg = document.getElementById('koko_guest_overlay_msg');
                var icon = document.getElementById('koko_guest_overlay_icon');
                var footer = document.getElementById('koko_guest_overlay_footer');
                var timerDiv = document.getElementById('koko_guest_overlay_timer');
                var timerSecs = document.getElementById('koko_guest_timer_seconds');

                var seconds = 5;
                timerDiv.style.display = 'block';
                timerSecs.innerText = seconds;

                if (data.status === 'SUCCESS') {
                    icon.innerHTML = '<i class="fas fa-check-circle fa-4x" style="color: #4caf50;"></i>Text';
                    icon.innerHTML = '<i class="fas fa-check-circle fa-4x" style="color: #4caf50;"></i>';
                    title.innerText = "{{ __('koko::lang.done') }}!";
                    msg.innerText = "{{ __('koko::lang.payment_successful') }}";
                    
                    var countdown = setInterval(function() {
                        seconds--;
                        timerSecs.innerText = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdown);
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    icon.innerHTML = '<i class="fas fa-exclamation-circle fa-4x" style="color: #f44336;"></i>';
                    title.innerText = "{{ __('koko::lang.payment_failed') }}";
                    msg.innerText = data.desc || "{{ __('koko::lang.something_went_wrong_during_payment') }}";
                    footer.style.display = 'block';
                    
                    var countdown = setInterval(function() {
                        seconds--;
                        timerSecs.innerText = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdown);
                            if (overlay.style.display === 'flex') {
                                overlay.style.display = 'none';
                                timerDiv.style.display = 'none';
                            }
                        }
                    }, 1000);
                }
            }
        })();
    </script>
@endif
