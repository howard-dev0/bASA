<?php
// config/constants.php

define('APP_NAME', 'bASA');
define('APP_FULL_NAME', 'Bestlink Automated System for Archives');
define('BASE_URL', '/bASA');

// Security & Cryptography Constants
define('SYSTEM_HMAC_KEY', 'bASA_BCP_2026_MASTER_SECRET_KEY_99482716');
define('SESSION_TIMEOUT', 900); // 15 minutes
define('TOTP_WINDOW', 30);      // 30-second refreshing Dynamic QR

// MailerSend Email API Configuration
define('MAILERSEND_API_TOKEN', 'mlsn.62316eb1881ba0b065051be110034b41b90396c0deb2efc386884f10c4c71d24');
define('MAILERSEND_FROM_EMAIL', 'info@test-ywj2lpn950kg7oqz.mlsender.net');
define('MAILERSEND_FROM_NAME', 'bASA BCP Library');

// 13 Academic Learning Book Subject Domains
define('BOOK_SUBJECTS', [
    'GENED'  => 'General Education (SH01)',
    'IT'     => 'Information Technology (SH02-F)',
    'CPE'    => 'Computer Engineering (SH02-B)',
    'BLIS'   => 'Library Science (SH03-F)',
    'PSYCH'  => 'Psychology (SH03-B)',
    'EDUC'   => "Teacher's Education (SH04)",
    'BA'     => 'Business Administration (SH05)',
    'ACC'     => 'Accounting (SH06-F)',
    'ENTREP' => 'Entrepreneurship (SH06-B)',
    'OA'     => 'Office Administration (SH07-F)',
    'TM'     => 'Tourism Management (SH07-B)',
    'HM'     => 'Hospitality Management (SH08)',
    'CRIM'   => 'Criminology (SH09)'
]);

// 23 BCP Degree Programs Grouped by Research Cluster
define('BCP_PROGRAMS', [
    'CIS' => [
        'label' => 'Cluster 1: Computing & Info Sciences (SH00)',
        'programs' => [
            'BSIT'  => 'BS in Information Technology',
            'BSCPE' => 'BS in Computer Engineering',
            'BLIS'  => 'Bachelor in Library & Information Science'
        ]
    ],
    'CTE' => [
        'label' => 'Cluster 2: Teacher Education (SH10 & SH11)',
        'programs' => [
            'BEED'      => 'Bachelor of Elementary Education',
            'BTLED'     => 'Bachelor of Technology & Livelihood Education',
            'BPED'      => 'Bachelor in Physical Education',
            'CPE'       => 'Certificate of Professional Education',
            'BSED-ENG'  => 'BSED Major in English',
            'BSED-FIL'  => 'BSED Major in Filipino',
            'BSED-MATH' => 'BSED Major in Mathematics',
            'BSED-SCI'  => 'BSED Major in Science',
            'BSED-SOC'  => 'BSED Major in Social Studies',
            'BSED-VAL'  => 'BSED Major in Values Education'
        ]
    ],
    'CBA' => [
        'label' => 'Cluster 3: Business, Accounting & Admin (SH12)',
        'programs' => [
            'BSAIS'    => 'BS in Accounting Information System',
            'BSENTREP' => 'BS in Entrepreneurship',
            'BSOA'     => 'BS in Office Administration',
            'BSBA-FM'  => 'BSBA Major in Financial Management',
            'BSBA-HRM' => 'BSBA Major in Human Resource Management',
            'BSBA-MM'  => 'BSBA Major in Marketing Management'
        ]
    ],
    'CHTM' => [
        'label' => 'Cluster 4: Hospitality & Tourism (SH13)',
        'programs' => [
            'BSHM' => 'BS in Hospitality Management',
            'BSTM' => 'BS in Tourism Management'
        ]
    ],
    'CCJE' => [
        'label' => 'Cluster 5: Criminal Justice Education (SH14-S1)',
        'programs' => [
            'BSCRIM' => 'BS in Criminology'
        ]
    ],
    'CAS' => [
        'label' => 'Cluster 6: Behavioral Sciences (SH14-S2)',
        'programs' => [
            'BSP' => 'BS in Psychology'
        ]
    ]
]);