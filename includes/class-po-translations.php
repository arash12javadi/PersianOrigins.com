<?php

/**
 * Custom translations for Persian Origins Plugin
 */

defined('ABSPATH') || exit;

class Persian_Origins_Translations
{

    /**
     * Return translation dictionary
     */
    public static function dictionary(): array
    {
        return [
            'fa' => [
                'Log in'     => 'ورود',
                'Log out'    => 'خروج',
                'Search'     => 'جستجو',
                'Search …'   => 'جستجو …',
                'Home'       => 'خانه',
                'Profile'    => 'پروفایل',
                'Settings'   => 'تنظیمات',
                'Submit'     => 'ارسال',
                'Current: English' => 'وضعیت: انگلیسی',
                'Current: Persian' => 'وضعیت: فارسی',
                'Switch to English' => 'تغییر به انگلیسی',
                'Switch to Persian' => 'تغییر به فارسی',
            ],
            'en' => [
                'ورود'   => 'Log in',
                'خروج'  => 'Log out',
                'جستجو' => 'Search',
                'خانه'  => 'Home',
                'پروفایل' => 'Profile',
                'تنظیمات' => 'Settings',
                'ارسال'   => 'Submit',
                'وضعیت: فارسی' => 'Current: Persian',
                'وضعیت: انگلیسی' => 'Current: English',
                'تغییر به فارسی' => 'Switch to Persian',
                'تغییر به انگلیسی' => 'Switch to English',
            ],
        ];
    }

    public static function apply_custom_replacements($html, $lang)
    {
        $replacements = [
            'fa' => [
                'Logout' => 'خروج',
                'Log in' => 'ورود',
                'Edit Profile' => 'ویرایش پروفایل',
            ],
            'en' => [
                'خروج' => 'Logout',
                'ورود' => 'Log in',
                'ویرایش پروفایل' => 'Edit Profile',
            ],
        ];
        return strtr($html, $replacements[$lang] ?? []);
    }
}
