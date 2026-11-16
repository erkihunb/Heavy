<?php

/*
 * The signed-in shell ported from the source system (resources/js/shell): sidebar, top
 * bar, breadcrumbs, appearance and language controls, the notification bell
 * and the kit's built-in labels. Page text belongs in the page's own file.
 */

return [
    'common' => [
        'back' => 'ተመለስ',
        'cancel' => 'ይቅር',
        'clear' => 'አጽዳ',
        'confirm' => 'አረጋግጥ',
        'filter' => 'አጣራ',
        'filters' => 'ማጣሪያዎች',
        'loading' => 'በመጫን ላይ',
        'no_results' => 'ምንም ውጤት አልተገኘም',
        'next' => 'ቀጣይ',
        'page' => 'ገጽ',
        'pagination' => 'ገጾች',
        'previous' => 'ቀዳሚ',
        'pagination_range' => ':from–:to ከ:total',
        'remove' => 'አስወግድ',
        'replace' => 'ተካ',
        'required' => 'የግድ ነው',
        'reset' => 'ዳግም አስጀምር',
        'results' => 'ውጤቶች',
        'search' => 'ፈልግ',
        'user' => 'ተጠቃሚ',
        'reason' => 'ምክንያት',
    ],

    // Labels the UI kit (packages/app-ui) shows on its own.
    'ui' => [
        'actions' => 'ተግባራት',
        'columns' => 'ዓምዶች',
        'show_all_columns' => 'ሁሉንም ዓምዶች አሳይ',
        'density' => 'የረድፍ ጥግግት',
        'compact' => 'የታመቀ',
        'comfortable' => 'ምቹ',
        'sticky_header' => 'ራስጌውን አቆይ',
        'reset_table' => 'ሰንጠረዡን ዳግም አስጀምር',
        'sorted_on_page' => 'በዚህ ገጽ ብቻ የተደረደረ',
        'sort_by' => 'በ:column ደርድር',
        'table_tools' => 'የሰንጠረዥ መሣሪያዎች',
        'file_max_size' => 'ከፍተኛው መጠን :size ሜባ',
    ],

    // Button labels that get the matching icon in the kit's Button (shell/Components/ui/actionIcons.tsx).
    'actions' => [
        'create' => 'ፍጠር',
        'add' => 'አክል',
        'save' => 'አስቀምጥ',
        'update' => 'አዘምን',
        'submit' => 'አስገባ',
        'apply' => 'ተግብር',
        'approve' => 'አጽድቅ',
        'close' => 'ዝጋ',
        'reject' => 'ውድቅ አድርግ',
        'edit' => 'አርትዕ',
        'delete' => 'ሰርዝ',
        'retry' => 'እንደገና ሞክር',
        'view' => 'ተመልከት',
        'details' => 'ዝርዝር',
        'export' => 'ላክ',
        'download' => 'አውርድ',
        'upload' => 'ስቀል',
        'print' => 'አትም',
        'continue' => 'ቀጥል',
        'copy' => 'ቅዳ',
        'send' => 'ላክ',
    ],

    'nav' => [
        'home' => 'መነሻ',
        'main_navigation' => 'ዋና ምናሌ',
        'open_menu' => 'ምናሌውን ይክፈቱ',
        'close_menu' => 'ምናሌውን ይዝጉ',
        'expand_sidebar' => 'የጎን ምናሌውን ያስፉ',
        'collapse_sidebar' => 'የጎን ምናሌውን ያጥብቡ',
        'filter_menu' => 'ምናሌውን ያጣሩ',
        'no_results' => 'የሚዛመድ ገጽ የለም።',
        'command_menu' => 'ወደ ገጽ ይሂዱ',
        'search_navigation' => 'ገጾችን ይፈልጉ…',
        'type_command_or_search' => 'የገጹን ስም ይጻፉ…',
        'hint_navigate' => 'ለመንቀሳቀስ',
        'hint_open' => 'ለመክፈት',
    ],

    // Groups of the sidebar menu (resources/js/config/navigation.ts).
    'menu' => [
        'permit_processing' => 'የፍቃድ ሂደት',
        'payments_reports' => 'ክፍያዎችና ሪፖርቶች',
        'front_desk' => 'የፊት ዴስክ',
        'access' => 'ተጠቃሚዎችና ኦዲት',
        'configuration' => 'ውቅር',
    ],

    'account' => [
        'menu' => 'የመለያ ምናሌ',
        'signed_in_as' => 'የገቡት በ',
        'profile_settings' => 'የመገለጫ ቅንብሮች',
        'sign_out' => 'ይውጡ',
    ],

    'appearance' => [
        'title' => 'ገጽታ',
        'mode' => 'ገጽታ',
        'system' => 'እንደ መሣሪያው',
        'light' => 'ብሩህ',
        'dark' => 'ጨለማ',
        'interface_style' => 'የገጽ ዘይቤ',
        'organization_default' => 'የተቋሙ ነባሪ',
        'styles' => [
            'authority' => 'ባለሥልጣን',
            'swiss' => 'ስዊስ',
            'classic' => 'ክላሲክ',
            'soft' => 'ለስላሳ',
            'contrast' => 'ከፍተኛ ንፅፅር',
        ],
    ],

    'language' => [
        'label' => 'ቋንቋ',
    ],

    'notifications' => [
        'title' => 'ማሳወቂያዎች',
        'empty' => 'ምንም ማሳወቂያ የለም',
        'empty_hint' => 'ስለ ፍቃዶችዎ መረጃ በኢሜይልና በኤስኤምኤስ ይላካል።',
    ],

    'breadcrumbs' => [
        'label' => 'የገጽ መንገድ',
        'create' => 'ፍጠር',
        'edit' => 'አርትዕ',
        'details' => 'ዝርዝር',
    ],
];
