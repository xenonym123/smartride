<?php
// Home page. Anyone can open it.
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();

$title = 'Scan anytime, ride anywhere';
$page = 'home';
include '../views/public/home.php';
