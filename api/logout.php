<?php
/**
 * Logout API
 */

require '../config/session.php';

session_destroy();
header('Location: ../login.php');
exit;
?>
