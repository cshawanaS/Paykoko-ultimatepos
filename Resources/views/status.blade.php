@extends('layouts.guest')
@section('title', __('koko::lang.payment_status'))

@section('content')
<div id="minimal_processing" style="display: none; text-align: center; margin-top: 100px; font-family: 'Inter', sans-serif;">
    <i class="fa fa-refresh fa-spin fa-2x" style="color: #ff3399;"></i>
    <h3 style="margin-top: 20px; color: #333;">{{ __('koko::lang.processing_payment_status') }}...</h3>
    <p class="text-muted">{{ __('koko::lang.please_wait_closing_window') }}</p>
</div>

<div class="container" id="main_status_container">
    <div class="row" style="margin-top: 50px;">
        <div class="col-md-6 col-md-offset-3">
            @php
                $is_success = ($status === 'SUCCESS');
                $is_cancelled = ($status === 'CANCELLED');
                $theme_color = $is_success ? '#4caf50' : ($is_cancelled ? '#ff9800' : '#f44336');
                $border_color = $is_success ? '#4a148c' : ($is_cancelled ? '#e65100' : '#b71c1c');
                $icon = $is_success ? 'fa-check-circle' : ($is_cancelled ? 'fa-times-circle' : 'fa-exclamation-circle');
                $title = $is_success ? __('koko::lang.payment_successful') : ($is_cancelled ? __('koko::lang.payment_cancelled') : __('koko::lang.payment_failed'));
                $message = $is_success ? __('koko::lang.thank_you_for_your_payment') : ($is_cancelled ? __('koko::lang.payment_was_cancelled') : ($desc ?: __('koko::lang.something_went_wrong_during_payment')));
            @endphp

            <div class="box box-solid" style="border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid {{ $border_color }};">
                <div class="box-body text-center" style="padding: 40px 20px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas {{ $icon }}" style="font-size: 80px; color: {{ $theme_color }};"></i>
                    </div>
                    
                    <h2 style="font-weight: bold; color: #333;">{{ $title }}</h2>
                    <p class="text-muted" style="font-size: 16px;">{{ $message }}</p>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div class="row text-left" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px;">
                        <div class="col-xs-6">
                            <label class="text-muted" style="font-weight: normal; margin-bottom: 5px;">{{ __('sale.invoice_no') }}:</label>
                            <div style="font-weight: bold; color: #444;">{{ $transaction->invoice_no }}</div>
                        </div>
                        <div class="col-xs-6">
                            <label class="text-muted" style="font-weight: normal; margin-bottom: 5px;">{{ __('sale.total_amount') }}:</label>
                            <div style="font-weight: bold; color: #444;">@format_currency($transaction->final_total)</div>
                        </div>
                        <div class="col-xs-12" style="margin-top: 15px;">
                            <label class="text-muted" style="font-weight: normal; margin-bottom: 5px;">{{ __('lang_v1.payment_method') }}:</label>
                            <div style="font-weight: bold; color: #444;">Koko BNPL</div>
                        </div>
                        @if($payment)
                        <div class="col-xs-12" style="margin-top: 15px;">
                            <label class="text-muted" style="font-weight: normal; margin-bottom: 5px;">{{ __('lang_v1.transaction_id') }}:</label>
                            <div style="font-weight: bold; color: #444;">{{ $payment->payment_ref_no }}</div>
                        </div>
                        @endif
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-12">
                            <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'showInvoice'], ['token' => $transaction->invoice_token]) }}" class="btn btn-primary btn-block btn-lg" style="background-color: #4a148c; border: none; border-radius: 5px;">
                                <i class="fas fa-file-invoice"></i> {{ $is_success ? __('koko::lang.view_invoice') : __('koko::lang.back_to_invoice') }}
                            </a>
                        </div>
                    </div>
                    
                    <div style="margin-top: 40px;">
                        <img src="{{asset('modules/koko/img/koko-logo.png')}}" alt="Koko" style="max-height: 40px; opacity: 0.6;">
                    </div>
                </div>
            </div>
            
            <p class="text-center text-muted" style="margin-top: 20px;">
                &copy; {{ date('Y') }} {{ $transaction->business->name }}. All rights reserved.
            </p>
        </div>
    </div>
</div>

<script>
    (function() {
        // Hide main UI if in a popup to avoid redundancy
        if (window.opener) {
            document.getElementById('main_status_container').style.display = 'none';
            document.getElementById('minimal_processing').style.display = 'block';
        }

        // Send message to opener (parent window)
        if (window.opener) {
            try {
                window.opener.postMessage({
                    type: 'KOKO_PAYMENT_STATUS',
                    status: "{{ $status }}",
                    desc: "{{ $desc }}"
                }, window.location.origin);
                
                // Close the popup after a short delay to ensure the message is delivered
                setTimeout(function() {
                    window.close();
                }, 1500);
            } catch (e) {
                console.error("Failed to send message to parent window:", e);
            }
        }
    })();
</script>

<style>
    body {
        background-color: #f4f7f6;
    }
    .btn-primary:hover {
        opacity: 0.9;
    }
</style>
@endsection
