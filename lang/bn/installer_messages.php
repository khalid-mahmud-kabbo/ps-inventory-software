<?php

return [

    /*
     *
     * Shared translations.
     *
     */
    'title' => 'Laravel ইন্সটলার',
    'next' => 'পরবর্তী ধাপ',
    'back' => 'পূর্ববর্তী',
    'finish' => 'ইনস্টল করুন',
    'forms' => [
        'errorTitle' => 'নিম্নলিখিত ত্রুটি গুলি ঘটেছে:',
    ],

    /*
     *
     * Home page translations.
     *
     */
    'welcome' => [
        'templateTitle' => 'স্বাগতম',
        'title'   => 'Laravel ইন্সটলার',
        'message' => 'সহজ ইনস্টলেশন এবং সেটআপ উইজার্ড।',
        'next'    => 'প্রয়োজনীয়তা পরীক্ষা করুন',
    ],

    /*
     *
     * Requirements page translations.
     *
     */
    'requirements' => [
        'templateTitle' => 'ধাপ 1 | সার্ভার প্রয়োজনীয়তা',
        'title' => 'সার্ভার প্রয়োজনীয়তা',
        'next'    => 'অনুমতি পরীক্ষা করুন',
    ],

    /*
     *
     * Permissions page translations.
     *
     */
    'permissions' => [
        'templateTitle' => 'ধাপ 2 | অনুমতি',
        'title' => 'অনুমতি',
        'next' => 'পরিবেশ কনফিগার করুন',
    ],

    /*
     *
     * Environment page translations.
     *
     */
    'environment' => [
        'menu' => [
            'templateTitle' => 'ধাপ 3 | পরিবেশ সেটিংস',
            'title' => 'পরিবেশ সেটিংস',
            'desc' => 'আপনি কীভাবে অ্যাপ্লিকেশনের <code>.env</code> ফাইল কনফিগার করবেন তা নির্বাচন করুন।',
            'wizard-button' => 'ফর্ম উইজার্ড সেটআপ',
            'classic-button' => 'ক্লাসিক টেক্সট এডিটর',
        ],
        'wizard' => [
            'templateTitle' => 'ধাপ 3 | পরিবেশ সেটিংস | নির্দেশিত উইজার্ড',
            'title' => 'নির্দেশিত <code>.env</code> উইজার্ড',
            'tabs' => [
                'environment' => 'পরিবেশ',
                'database' => 'ডাটাবেস',
                'application' => 'অ্যাপ্লিকেশন',
            ],
            'form' => [
                'name_required' => 'পরিবেশের একটি নাম প্রয়োজন।',
                'app_name_label' => 'অ্যাপ নাম',
                'app_name_placeholder' => 'অ্যাপ নাম',
                'app_environment_label' => 'অ্যাপ পরিবেশ',
                'app_environment_label_local' => 'লোকাল',
                'app_environment_label_developement' => 'ডেভেলপমেন্ট',
                'app_environment_label_qa' => 'QA',
                'app_environment_label_production' => 'প্রোডাকশন',
                'app_environment_label_other' => 'অন্যান্য',
                'app_environment_placeholder_other' => 'আপনার পরিবেশ লিখুন...',
                'app_debug_label' => 'ডিবাগ মোড',
                'app_debug_label_true' => 'সত্য',
                'app_debug_label_false' => 'মিথ্যা',
                'app_log_level_label' => 'লগ স্তর',
                'app_log_level_label_debug' => 'ডিবাগ',
                'app_log_level_label_info' => 'ইনফো',
                'app_log_level_label_notice' => 'নোটিশ',
                'app_log_level_label_warning' => 'সতর্কতা',
                'app_log_level_label_error' => 'ত্রুটি',
                'app_log_level_label_critical' => 'ক্রিটিক্যাল',
                'app_log_level_label_alert' => 'সতর্কবার্তা',
                'app_log_level_label_emergency' => 'জরুরি',
                'app_url_label' => 'অ্যাপ URL',
                'app_url_placeholder' => 'অ্যাপ URL',
                'db_connection_failed' => 'ডাটাবেসে সংযোগ সম্ভব হয়নি।',
                'db_connection_label' => 'ডাটাবেস সংযোগ',
                'db_connection_label_mysql' => 'MySQL',
                'db_connection_label_sqlite' => 'SQLite',
                'db_connection_label_pgsql' => 'PostgreSQL',
                'db_connection_label_sqlsrv' => 'SQL Server',
                'db_host_label' => 'ডাটাবেস হোস্ট',
                'db_host_placeholder' => 'ডাটাবেস হোস্ট',
                'db_port_label' => 'ডাটাবেস পোর্ট',
                'db_port_placeholder' => 'ডাটাবেস পোর্ট',
                'db_name_label' => 'ডাটাবেস নাম',
                'db_name_placeholder' => 'ডাটাবেস নাম',
                'db_username_label' => 'ডাটাবেস ব্যবহারকারী নাম',
                'db_username_placeholder' => 'ডাটাবেস ব্যবহারকারী নাম',
                'db_password_label' => 'ডাটাবেস পাসওয়ার্ড',
                'db_password_placeholder' => 'ডাটাবেস পাসওয়ার্ড',

                'app_tabs' => [
                    'more_info' => 'আরও তথ্য',
                    'broadcasting_title' => 'ব্রডকাস্টিং, ক্যাশিং, সেশন, এবং কিউ',
                    'broadcasting_label' => 'ব্রডকাস্ট ড্রাইভার',
                    'broadcasting_placeholder' => 'ব্রডকাস্ট ড্রাইভার',
                    'cache_label' => 'ক্যাশ ড্রাইভার',
                    'cache_placeholder' => 'ক্যাশ ড্রাইভার',
                    'session_label' => 'সেশন ড্রাইভার',
                    'session_placeholder' => 'সেশন ড্রাইভার',
                    'queue_label' => 'কিউ ড্রাইভার',
                    'queue_placeholder' => 'কিউ ড্রাইভার',
                    'redis_label' => 'Redis ড্রাইভার',
                    'redis_host' => 'Redis হোস্ট',
                    'redis_password' => 'Redis পাসওয়ার্ড',
                    'redis_port' => 'Redis পোর্ট',

                    'mail_label' => 'মেইল',
                    'mail_driver_label' => 'মেইল ড্রাইভার',
                    'mail_driver_placeholder' => 'মেইল ড্রাইভার',
                    'mail_host_label' => 'মেইল হোস্ট',
                    'mail_host_placeholder' => 'মেইল হোস্ট',
                    'mail_port_label' => 'মেইল পোর্ট',
                    'mail_port_placeholder' => 'মেইল পোর্ট',
                    'mail_username_label' => 'মেইল ব্যবহারকারী নাম',
                    'mail_username_placeholder' => 'মেইল ব্যবহারকারী নাম',
                    'mail_password_label' => 'মেইল পাসওয়ার্ড',
                    'mail_password_placeholder' => 'মেইল পাসওয়ার্ড',
                    'mail_encryption_label' => 'মেইল এনক্রিপশন',
                    'mail_encryption_placeholder' => 'মেইল এনক্রিপশন',

                    'pusher_label' => 'Pusher',
                    'pusher_app_id_label' => 'Pusher অ্যাপ আইডি',
                    'pusher_app_id_palceholder' => 'Pusher অ্যাপ আইডি',
                    'pusher_app_key_label' => 'Pusher অ্যাপ কী',
                    'pusher_app_key_palceholder' => 'Pusher অ্যাপ কী',
                    'pusher_app_secret_label' => 'Pusher অ্যাপ সিক্রেট',
                    'pusher_app_secret_palceholder' => 'Pusher অ্যাপ সিক্রেট',
                ],
                'buttons' => [
                    'setup_database' => 'ডাটাবেস সেটআপ করুন',
                    'setup_application' => 'অ্যাপ্লিকেশন সেটআপ করুন',
                    'install' => 'ইনস্টল করুন',
                ],
            ],
        ],
        'classic' => [
            'templateTitle' => 'ধাপ 3 | পরিবেশ সেটিংস | ক্লাসিক এডিটর',
            'title' => 'ক্লাসিক পরিবেশ এডিটর',
            'save' => '.env সংরক্ষণ করুন',
            'back' => 'ফর্ম উইজার্ড ব্যবহার করুন',
            'install' => 'সংরক্ষণ ও ইনস্টল করুন',
        ],
        'success' => 'আপনার .env ফাইলের সেটিংস সংরক্ষিত হয়েছে।',
        'errors' => '.env ফাইল সংরক্ষণ করা যায়নি, অনুগ্রহ করে ম্যানুয়ালি তৈরি করুন।',
    ],

    'install' => 'ইনস্টল করুন',

    /*
     *
     * Installed Log translations.
     *
     */
    'installed' => [
        'success_log_message' => 'Laravel ইন্সটলার সফলভাবে ইনস্টল হয়েছে ',
    ],

    /*
     *
     * Final page translations.
     *
     */
    'final' => [
        'title' => 'ইনস্টলেশন সম্পন্ন',
        'templateTitle' => 'ইনস্টলেশন সম্পন্ন',
        'finished' => 'অ্যাপ্লিকেশন সফলভাবে ইনস্টল হয়েছে।',
        'migration' => 'মাইগ্রেশন ও সিড কনসোল আউটপুট:',
        'console' => 'অ্যাপ্লিকেশন কনসোল আউটপুট:',
        'log' => 'ইনস্টলেশন লগ এন্ট্রি:',
        'env' => 'চূড়ান্ত .env ফাইল:',
        'exit' => 'বাহির হওয়ার জন্য এখানে ক্লিক করুন',
    ],

    /*
     *
     * Update specific translations
     *
     */
    'updater' => [
        'title' => 'Laravel আপডেটার',
        'welcome' => [
            'title'   => 'আপডেটার-এ স্বাগতম',
            'message' => 'আপডেট উইজার্ডে স্বাগতম।',
        ],
        'overview' => [
            'title'   => 'ওভারভিউ',
            'message' => '1টি আপডেট আছে।|:number টি আপডেট আছে।',
            'install_updates' => 'আপডেট ইনস্টল করুন',
        ],
        'final' => [
            'title' => 'সম্পন্ন',
            'finished' => 'অ্যাপ্লিকেশনের ডাটাবেস সফলভাবে আপডেট হয়েছে।',
            'exit' => 'বাহির হওয়ার জন্য এখানে ক্লিক করুন',
        ],
        'log' => [
            'success_message' => 'Laravel Installer সফলভাবে আপডেট হয়েছে ',
        ],
    ],

];
