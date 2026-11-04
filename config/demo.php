<?php
/** Explicit demo switch; never applies to password-reset or note PIN codes. */
function notezy_demo_otp_enabled(): bool {
    return filter_var(getenv('NOTEZY_DEMO_OTP') ?: 'false', FILTER_VALIDATE_BOOLEAN);
}
function notezy_activation_otp(): string {
    return notezy_demo_otp_enabled() ? '123456' : (string) random_int(100000, 999999);
}
