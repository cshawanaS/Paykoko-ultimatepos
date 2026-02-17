@extends('layouts.app')
@section('title', 'Koko Settings')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Koko Settings</h1>
</section>

<!-- Main content -->
<section class="content">
    {!! Form::open(['url' => action('\Modules\Koko\Http\Controllers\KokoController@updateSettings'), 'method' => 'post', 'id' => 'koko_settings_form']) !!}
    <div class="box box-solid">
        <div class="box-header">
            <h3 class="box-title">API Configuration</h3>
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
                        {!! Form::select('mode', ['sandbox' => 'Sandbox', 'live' => 'Live'], $koko_setting->mode, ['class' => 'form-control', 'required']) !!}
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
        <div class="box-header">
            <h3 class="box-title">Payment Settings</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('payment_method', 'Payment Method:*') !!}
                        {!! Form::select('payment_method', $payment_methods, $koko_setting->payment_method, ['class' => 'form-control select2', 'style' => 'width:100%', 'required']) !!}
                        <p class="help-block">Select which payment method to record payments under</p>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('account_id', 'Default Account:') !!}
                        {!! Form::select('account_id', $accounts, $koko_setting->account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => 'Select Account']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('pos_account_id', 'POS Account:') !!}
                        {!! Form::select('pos_account_id', $accounts, $koko_setting->pos_account_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => 'Select Account']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header">
            <h3 class="box-title">Convenience Fee Settings</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('enable_fee', 1, $koko_setting->enable_fee, ['class' => 'input-icheck']) !!} Enable Convenience Fee
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('fee_percentage', 'Fee Percentage (%):') !!}
                        {!! Form::text('fee_percentage', @num_format($koko_setting->fee_percentage), ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('max_fee_amount', 'Max Fee Amount:') !!}
                        {!! Form::text('max_fee_amount', @num_format($koko_setting->max_fee_amount), ['class' => 'form-control input_number', 'placeholder' => '0.00 (0 for no limit)']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <p class="text-muted"><i class="fa fa-info-circle"></i> This fee will be added to the invoice total before redirecting to Koko. After successful payment, the fee will be recorded as a miscellaneous charge or adjustment to the invoice.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 text-center">
            <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
        </div>
    </div>
    {!! Form::close() !!}
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
