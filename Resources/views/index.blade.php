@extends('layouts.app')
@section('title', 'Koko Settings')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Koko Settings</h1>
</section>

<!-- Main content -->
<section class="content">
    {!! Form::open(['url' => action([\Modules\Koko\Http\Controllers\KokoController::class, 'updateSettings']), 'method' => 'post', 'id' => 'koko_settings_form']) !!}
    
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fas fa-rocket"></i> Getting Started with Koko</h3>
                </div>
                <div class="box-body">
                    <ol>
                        <li><strong>Rename Payment Label:</strong> Go to <a href="{{action([\App\Http\Controllers\BusinessController::class, 'getBusinessSettings'])}}#custom_labels_tab" target="_blank">Settings > Business Settings > Custom Labels</a> and rename <strong>"Custom Payment X"</strong> to <strong>"Koko"</strong>.</li>
                        <li><strong>Setup Internal Account:</strong> Go to <a href="{{action([\App\Http\Controllers\AccountController::class, 'index'])}}" target="_blank">Account Management > List Accounts</a> and create a Bank/Cash account (e.g., "Koko Account").</li>
                        <li><strong>Link Account:</strong> Select your newly created account in the <strong>"Internal Account Mapping"</strong> dropdown below and click <strong>"Save Settings"</strong>.</li>
                    </ol>
                    <p class="text-muted"><i class="fas fa-info-circle"></i> This ensures that payments are correctly labeled in your reports and linked to your financial accounts.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fas fa-cog"></i> Koko Configuration</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('merchant_id', 'Merchant ID:*') !!}
                        {!! Form::text('merchant_id', $koko_setting->merchant_id, ['class' => 'form-control', 'required', 'placeholder' => 'Enter Koko Merchant ID']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('api_key', 'API Key:*') !!}
                        {!! Form::text('api_key', $koko_setting->api_key, ['class' => 'form-control', 'placeholder' => 'Enter Koko API Key']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('mode', 'Mode:*') !!}
                        {!! Form::select('mode', ['sandbox' => 'Sandbox', 'live' => 'Live'], $koko_setting->mode, ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('public_key', 'Public Key (PEM format):*') !!}
                        {!! Form::textarea('public_key', $koko_setting->public_key, ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Paste Koko Public Key here']) !!}
                        <p class="help-block">Used for webhook signature verification</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('private_key', 'Private Key (PEM format):*') !!}
                        {!! Form::textarea('private_key', $koko_setting->private_key, ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Paste Koko Private Key here']) !!}
                        <p class="help-block">Used for signing payment requests</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fas fa-link"></i> Account & Slot Mapping</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('payment_method', 'Payment Slot Mapping:*') !!}
                        {!! Form::select('payment_method', $payment_methods, $koko_setting->payment_method, ['class' => 'form-control select2', 'style' => 'width:100%', 'required']) !!}
                        <p class="help-block">Select an empty "Custom Payment" slot to use for Koko.</p>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('payment_label', 'Display Label:') !!}
                        @php
                            $current_slot = $koko_setting->payment_method ?? 'custom_pay_1';
                            $default_label = $custom_labels['payments'][$current_slot] ?? 'Koko';
                        @endphp
                        {!! Form::text('payment_label', $default_label, ['class' => 'form-control', 'placeholder' => 'e.g. Koko']) !!}
                        <p class="help-block">This will update the label in your POS and Reports.</p>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('pos_account_id', 'Internal Account Mapping:') !!}
                        {!! Form::select('pos_account_id', $accounts, $koko_setting->pos_account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => 'Select Account']) !!}
                        <p class="help-block">Payments made via Koko will be recorded against this internal POS account.</p>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('account_id', 'Default Account (Non-POS):') !!}
                        {!! Form::select('account_id', $accounts, $koko_setting->account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => 'Select Account']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fas fa-hand-holding-usd"></i> Convenience Fee Settings</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('enable_fee', 1, $koko_setting->enable_fee, ['class' => 'input-icheck']) !!} <strong>Enable Convenience Fee</strong>
                            </label>
                        </div>
                        <p class="help-block">Charge convenience fee on Koko payments</p>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('fee_percentage', 'Fee Percentage (%):') !!}
                        {!! Form::text('fee_percentage', @num_format($koko_setting->fee_percentage), ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                        <p class="help-block">Percentage of invoice to charge as convenience fee.</p>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('max_fee_amount', 'Max Fee Amount:') !!}
                        {!! Form::text('max_fee_amount', @num_format($koko_setting->max_fee_amount), ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                        <p class="help-block">Maximum fee to charge. Set 0 for no limit.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <p class="text-muted"><i class="fa fa-info-circle"></i> This fee will be added to the invoice total before redirecting to Koko.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 text-center">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </div>
    {!! Form::close() !!}

    <div class="row" style="margin-top: 30px;">
        <div class="col-md-12 text-center">
            <img src="{{asset('modules/koko/img/koko-logo.png')}}" alt="Koko BNPL" style="max-width: 250px; width: 100%;">
        </div>
    </div>
</section>
<!-- /.content -->

@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        // Any custom JS for settings page if needed
    });
</script>
@endsection
