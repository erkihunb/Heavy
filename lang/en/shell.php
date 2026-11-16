<?php

/*
 * The signed-in shell ported from the source system (resources/js/shell): sidebar, top
 * bar, breadcrumbs, appearance and language controls, the notification bell
 * and the kit's built-in labels. Page text belongs in the page's own file.
 */

return [
    'common' => [
        'back' => 'Back',
        'cancel' => 'Cancel',
        'clear' => 'Clear',
        'confirm' => 'Confirm',
        'filter' => 'Filter',
        'filters' => 'Filters',
        'loading' => 'Loading',
        'no_results' => 'No results found',
        'next' => 'Next',
        'page' => 'Page',
        'pagination' => 'Pagination',
        'previous' => 'Previous',
        'pagination_range' => ':from–:to of :total',
        'remove' => 'Remove',
        'replace' => 'Replace',
        'required' => 'required',
        'reset' => 'Reset',
        'results' => 'results',
        'search' => 'Search',
        'user' => 'User',
        'reason' => 'Reason',
    ],

    // Labels the UI kit (packages/app-ui) shows on its own.
    'ui' => [
        'actions' => 'Actions',
        'columns' => 'Columns',
        'show_all_columns' => 'Show all columns',
        'density' => 'Row density',
        'compact' => 'Compact',
        'comfortable' => 'Comfortable',
        'sticky_header' => 'Keep header visible',
        'reset_table' => 'Reset table',
        'sorted_on_page' => 'Sorted on this page only',
        'sort_by' => 'Sort by :column',
        'table_tools' => 'Table tools',
        'file_max_size' => ':size MB maximum',
    ],

    // Button labels that get the matching icon in the kit's Button (shell/Components/ui/actionIcons.tsx).
    'actions' => [
        'create' => 'Create',
        'add' => 'Add',
        'save' => 'Save',
        'update' => 'Update',
        'submit' => 'Submit',
        'apply' => 'Apply',
        'approve' => 'Approve',
        'close' => 'Close',
        'reject' => 'Reject',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'retry' => 'Try again',
        'view' => 'View',
        'details' => 'Details',
        'export' => 'Export',
        'download' => 'Download',
        'upload' => 'Upload',
        'print' => 'Print',
        'continue' => 'Continue',
        'copy' => 'Copy',
        'send' => 'Send',
    ],

    'nav' => [
        'home' => 'Home',
        'main_navigation' => 'Main navigation',
        'open_menu' => 'Open the menu',
        'close_menu' => 'Close the menu',
        'expand_sidebar' => 'Expand the sidebar',
        'collapse_sidebar' => 'Collapse the sidebar',
        'filter_menu' => 'Filter the menu',
        'no_results' => 'No page matches.',
        'command_menu' => 'Go to a page',
        'search_navigation' => 'Search pages…',
        'type_command_or_search' => 'Type a page name…',
        'hint_navigate' => 'move',
        'hint_open' => 'open',
    ],

    // Groups of the sidebar menu (resources/js/config/navigation.ts).
    'menu' => [
        'permit_processing' => 'Permit processing',
        'payments_reports' => 'Payments and reports',
        'front_desk' => 'Front desk',
        'access' => 'Users and audit',
        'configuration' => 'Configuration',
    ],

    'account' => [
        'menu' => 'Account menu',
        'signed_in_as' => 'Signed in as',
        'profile_settings' => 'Profile settings',
        'sign_out' => 'Log out',
    ],

    'appearance' => [
        'title' => 'Appearance',
        'mode' => 'Theme',
        'system' => 'System',
        'light' => 'Light',
        'dark' => 'Dark',
        'interface_style' => 'Interface style',
        'organization_default' => 'Organisation default',
        'styles' => [
            'authority' => 'Authority',
            'swiss' => 'Swiss',
            'classic' => 'Classic',
            'soft' => 'Soft UI',
            'contrast' => 'High contrast',
        ],
    ],

    'language' => [
        'label' => 'Language',
    ],

    'notifications' => [
        'title' => 'Notifications',
        'empty' => 'No notifications',
        'empty_hint' => 'Updates about your permits are sent by email and SMS.',
    ],

    'breadcrumbs' => [
        'label' => 'Breadcrumb',
        'create' => 'Create',
        'edit' => 'Edit',
        'details' => 'Details',
    ],
];
