<?php
require_once __DIR__ . '/../config/config.php';
webLogout();
header('Location: /admin/login.php');
exit;
