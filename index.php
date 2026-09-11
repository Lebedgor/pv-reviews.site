<?php
/**
 * PVR Media Reviews — Entry Point
 * All requests are routed through this file.
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/pages/layout.php';
