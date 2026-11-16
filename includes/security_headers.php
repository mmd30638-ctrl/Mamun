<?php
/**
 * Security Headers — APONSHOP International Standards
 * v10.98.858
 * No financial / sales / stock logic touched.
 */
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}
