<?php
// Legacy links cannot activate an account without its current email OTP.
header('Location: register.php');
exit;
