<?php

namespace Modules\Koko\Services;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\Log;
use Modules\Koko\Entities\KokoSetting;

class KokoService
{
    protected $transactionUtil;

    public function __construct()
    {
        $this->transactionUtil = new TransactionUtil();
    }

    /**
     * Generate Payment Data for Koko Form
     *
     * @param object $transaction
     * @return array
     */
    public function getPaymentData($transaction)
    {
        $business_id = $transaction->business_id;
        $koko_setting = KokoSetting::where('business_id', $business_id)->first();

        if (empty($koko_setting) || empty($koko_setting->merchant_id)) {
            return ['error' => 'Koko settings not configured'];
        }

        // Ensure business and currency relationships are loaded
        if (!$transaction->relationLoaded('business.currency')) {
            $transaction->load(['business.currency', 'contact']);
        }

        $koko_currency = $transaction->business->currency->code ?? 'LKR';
        
        // Calculate remaining balance
        $paid_amount = \App\TransactionPayment::where('transaction_id', $transaction->id)->sum('amount');
        $remaining_balance = $transaction->final_total - $paid_amount;
        
        // Use fee service
        $feeService = new KokoFeeService();
        $feeData = $feeService->calculateConvenienceFee($remaining_balance, $koko_setting);
        
        $koko_amount = $feeData['koko_amount'];
        $koko_order_id = $transaction->id;
        
        // Platform Spoofing logic (Aligned with reference plugin)
        $pluginName = "woocommerce";
        $pluginVersion = "8.6.0";
        
        // REFERENCE NOTE: The WordPress plugin uses the same URL for all three parameters
        $returnUrl = route('koko.return', ['id' => $transaction->id]);
        $responseUrl = route('koko.notify');
        $cancelUrl = $returnUrl;
        
        $reference = $koko_setting->merchant_id . rand(111, 999) . '-' . $transaction->invoice_no;
        $firstName = !empty($transaction->contact->first_name) ? $transaction->contact->first_name : $transaction->contact->name;
        $lastName = $transaction->contact->last_name ?? '';
        $email = $transaction->contact->email ?? '';
        $productName = "Invoice " . $transaction->invoice_no;
        $apiKey = $koko_setting->api_key;
        
        // Data string for signature
        $dataString = $koko_setting->merchant_id . $koko_amount . $koko_currency . $pluginName . $pluginVersion . 
                      $returnUrl . $responseUrl . $koko_order_id . $reference . $firstName . 
                      $lastName . $email . $productName . $apiKey . $cancelUrl;

        Log::debug("Koko API DataString Detail:", [
            'mId' => $koko_setting->merchant_id,
            'amount' => $koko_amount,
            'currency' => $koko_currency,
            'pluginName' => $pluginName,
            'pluginVersion' => $pluginVersion,
            'returnUrl' => $returnUrl,
            'responseUrl' => $responseUrl,
            'orderId' => $koko_order_id,
            'reference' => $reference,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'productName' => $productName,
            'apiKey' => $apiKey,
            'cancelUrl' => $cancelUrl
        ]);

        $privateKey = $koko_setting->private_key;
        $signatureEncoded = $this->generateSignature($dataString, $privateKey);

        return [
            'action_url' => ($koko_setting->mode == 'live') ? 'https://prodapi.paykoko.com/api/merchants/orderCreate' : 'https://devapi.paykoko.com/api/merchants/orderCreate',
            'fields' => [
                '_mId' => $koko_setting->merchant_id,
                'api_key' => $apiKey,
                '_returnUrl' => $returnUrl,
                '_responseUrl' => $responseUrl,
                '_currency' => $koko_currency,
                '_amount' => $koko_amount,
                '_reference' => $reference,
                '_pluginName' => $pluginName,
                '_pluginVersion' => $pluginVersion,
                '_cancelUrl' => $cancelUrl,
                '_orderId' => $koko_order_id,
                '_firstName' => $firstName,
                '_lastName' => $lastName,
                '_email' => $email,
                '_description' => $productName,
                'dataString' => $dataString,
                'signature' => $signatureEncoded,
                '_mobileNo' => $transaction->contact->mobile
            ],
            'fee_data' => $feeData,
            'installment_amount' => number_format((float)$feeData['total_with_fee'] / 3, 2, '.', ','),
            'currency' => $koko_currency
        ];
    }

    /**
     * Generate RSA signature
     */
    protected function generateSignature($data, $privateKey)
    {
        $pkeyid = openssl_get_privatekey($privateKey);
        $signatureEncoded = "";
        if ($pkeyid) {
            openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
            $signatureEncoded = base64_encode($signature);
            openssl_free_key($pkeyid);
        }
        return $signatureEncoded;
    }

    /**
     * Validate incoming signature
     */
    public function validateSignature($data, $signature, $publicKey)
    {
        $pubKeyid = openssl_get_publickey($publicKey);
        if (!$pubKeyid) {
            return false;
        }
        $signature = base64_decode($signature);
        $result = openssl_verify($data, $signature, $pubKeyid, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKeyid);
        return $result === 1;
    }
}
