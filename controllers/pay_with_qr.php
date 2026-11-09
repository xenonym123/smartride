<?php
// Pay with QR page (replaces the old 1TAP Card page). Anyone can open it.
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();

$title = 'Pay with QR';
$page = 'qr';
include '../views/public/pay_with_qr.php';
