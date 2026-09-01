<?php

return [
    'broker' => [
        /*
         * fake      — in-process sample data (local Mac / tests)
         * in-process — run broker Kernel without sudo (dev against fixtures)
         * sudo      — production: sudo /usr/local/lib/lacmp-panel/broker
         */
        'driver' => env('BROKER_DRIVER', 'fake'),
        'path' => env('BROKER_PATH', '/usr/local/lib/lacmp-panel/broker'),
        'use_sudo' => env('BROKER_USE_SUDO', true),
        'sudo_path' => env('BROKER_SUDO_PATH', '/usr/bin/sudo'),
        'timeout' => (int) env('BROKER_TIMEOUT', 45),
    ],

    'www_root' => env('LACMP_WWW_ROOT', '/data/www'),

    'session_idle_minutes' => (int) env('LACMP_SESSION_IDLE', 15),

    /**
     * Single source of truth for TOTP (installer writes PANEL_REQUIRE_TOTP).
     * true: setup and login force enrollment. false: password-only (optional enroll).
     * Default true when the env key is missing (safer for a leftover .env).
     */
    'require_totp' => filter_var(env('PANEL_REQUIRE_TOTP', true), FILTER_VALIDATE_BOOL),

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
        'lockout_attempts' => 10,
        'lockout_minutes' => 15,
    ],
];
