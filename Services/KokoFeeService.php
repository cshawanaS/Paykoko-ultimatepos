<?php

namespace Modules\Koko\Services;

class KokoFeeService
{
    /**
     * Calculate convenience fee based on settings
     *
     * @param float $amount
     * @param object $settings
     * @return array
     */
    public function calculateConvenienceFee($amount, $settings)
    {
        $convenience_fee = 0;
        $total_payable = $amount;
        $fee_display_percent = 0;

        if (!empty($settings) && $settings->enable_fee && $settings->fee_percentage > 0) {
            $fee_display_percent = $settings->fee_percentage;
            $convenience_fee = ($amount * ($settings->fee_percentage / 100));

            // Apply max cap if set
            if ($settings->max_fee_amount > 0 && $convenience_fee > $settings->max_fee_amount) {
                $convenience_fee = $settings->max_fee_amount;
            }
        }

        $total_with_fee = $amount + $convenience_fee;

        return [
            'total_payable' => $amount,
            'convenience_fee' => (float)$convenience_fee,
            'total_with_fee' => (float)$total_with_fee,
            'fee_display_percent' => (float)$fee_display_percent,
            'koko_amount' => number_format((float)$total_with_fee, 2, '.', '')
        ];
    }
}
