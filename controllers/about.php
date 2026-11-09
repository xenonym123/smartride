<?php
// About Us page. Anyone can open it.
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();

$title = 'About us';
$page = 'about';
include '../views/public/about.php';
