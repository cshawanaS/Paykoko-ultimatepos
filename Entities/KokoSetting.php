<?php

namespace Modules\Koko\Entities;

use Illuminate\Database\Eloquent\Model;

class KokoSetting extends Model
{
    protected $table = 'koko_module_settings';

    protected $fillable = [
        'business_id',
        'merchant_id',
        'api_key',
        'public_key',
        'private_key',
        'account_id',
        'pos_account_id',
        'mode',
        'payment_method',
        'fee_percentage',
        'max_fee_amount',
        'enable_fee'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'api_key' => 'encrypted',
        'public_key' => 'encrypted',
        'private_key' => 'encrypted',
        'fee_percentage' => 'float',
        'max_fee_amount' => 'float',
        'enable_fee' => 'boolean',
    ];

    /**
     * Default values for attributes
     */
    protected $attributes = [
        'fee_percentage' => 0.00,
        'max_fee_amount' => 0,
        'enable_fee' => false,
        'mode' => 'sandbox',
    ];
}
