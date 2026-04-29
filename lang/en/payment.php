<?php

return [
    // Payment
    'payment'               => 'Payment',
    'payment_method'        => 'Payment Method',
    'select_payment_method' => 'Select Payment Method',
    'payment_details'       => 'Payment Details',
    'amount_to_pay'         => 'Amount to Pay',

    // Methods
    'promptpay'             => 'PromptPay',
    'bank_transfer'         => 'Bank Transfer',
    'credit_card'           => 'Credit/Debit Card',
    'true_money'            => 'TrueMoney Wallet',
    'line_pay'              => 'LINE Pay',
    'paypal'                => 'PayPal',
    'omise'                 => 'Credit Card (Omise)',
    'stripe'                => 'Credit Card (Stripe)',

    // PromptPay
    'scan_qr_code'          => 'Scan QR Code',
    'qr_amount'             => 'Amount :amount',
    'qr_expires_in'         => 'Expires in :time',
    'after_payment_upload_slip' => 'After transferring, please upload your slip below',

    // Bank Transfer
    'bank_details'          => 'Bank Details',
    'bank_name'             => 'Bank Name',
    'account_number'        => 'Account Number',
    'account_name'          => 'Account Name',
    'copy_account_number'   => 'Copy Account Number',
    'copied'                => 'Copied!',

    // Slip upload
    'upload_slip'           => 'Upload Slip',
    'upload_slip_hint'      => 'Upload payment slip to confirm your transfer',
    'select_file'           => 'Select File',
    'drag_drop_slip'        => 'Drag and drop slip here',
    'slip_requirements'     => 'Supports: JPG, PNG, max 5MB',
    'transfer_amount'       => 'Transfer Amount',
    'transfer_date'         => 'Transfer Date',
    'ref_code'              => 'Reference Code',
    'ref_code_optional'     => 'Reference Code (optional)',
    'submit_slip'           => 'Submit Slip',
    'slip_submitted'        => 'Slip submitted',
    'slip_verifying'        => 'Verifying slip...',
    'slip_approved'         => 'Slip approved',
    'slip_rejected'         => 'Slip rejected',

    // Status
    'payment_pending'       => 'Pending Payment',
    'payment_success'       => 'Payment Successful',
    'payment_failed'        => 'Payment Failed',
    'payment_expired'       => 'Payment Expired',
    'payment_cancelled'     => 'Payment Cancelled',
    'payment_refunded'      => 'Refunded',

    // Messages
    'pay_now'               => 'Pay Now',
    'payment_success_message' => 'Thank you for your purchase! Your photos are ready to download.',
    'payment_failed_message' => 'Payment failed. Please try again.',
    'check_email'           => 'Please check your email for payment confirmation',

    // Security notice
    'secure_payment'        => 'Secure Payment',
    'ssl_encrypted'         => 'SSL Encrypted',
    'data_protected'        => 'Your data is protected',
];
