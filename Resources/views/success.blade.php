@extends('layouts.guest')
@section('title', __('koko::lang.payment_successful'))

@section('content')
<div class="container">
    <div class="row" style="margin-top: 50px;">
        <div class="col-md-6 col-md-offset-3">
            <div class="box box-solid" style="border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #4a148c;">
                <div class="box-body text-center" style="padding: 40px 20px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 80px; color: #4caf50;"></i>
                    </div>
                    
                    <h2 style="font-weight: bold; color: #333;">{{ __('koko::lang.payment_successful') }}!</h2>
                    <p class="text-muted" style="font-size: 16px;">{{ __('koko::lang.thank_you_for_your_payment') }}</p>
                    
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
                        <div class="col-sm-6" style="margin-bottom: 10px;">
                            <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'showInvoice'], ['token' => $transaction->invoice_token]) }}" class="btn btn-default btn-block btn-lg" style="border-radius: 5px;">
                                <i class="fas fa-file-invoice"></i> {{ __('koko::lang.view_invoice') }}
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="/pos" class="btn btn-primary btn-block btn-lg" style="background-color: #4a148c; border: none; border-radius: 5px;">
                                <i class="fas fa-shopping-cart"></i> {{ __('sale.pos') }}
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

<style>
    body {
        background-color: #f4f7f6;
    }
    .btn-primary:hover {
        background-color: #38006b !important;
    }
</style>
@endsection
