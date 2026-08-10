<?php
// Legacy ProPay/AKPay withdrawal router disabled.
// Final gateway policy: LG Pay is the only active/default payout gateway.
if (!function_exists('wcb_route_withdrawal')) {
    function wcb_route_withdrawal($conn, $uid, $amount, $wallet) {
        if (function_exists('lgpay_ensure_schema')) { @lgpay_ensure_schema($conn); }
        if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }
        return array('success' => false, 'message' => 'Legacy withdrawal router is disabled. Withdrawals must be admin-approved and sent through LG Pay only.');
    }
}
?>
