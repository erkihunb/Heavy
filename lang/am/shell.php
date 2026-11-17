<?php

/*
 * The signed-in shell ported from the source system (resources/js/shell): sidebar, top
 * bar, breadcrumbs, appearance and language controls, the notification bell
 * and the kit's built-in labels. Page text belongs in the page's own file.
 */

return [
    'common' => [
        'back' => 'ተመለስ',
        'cancel' => 'ይሰረዝ',
        'clear' => 'አጽዳ',
        'confirm' => 'አረጋግጥ',
        'filter' => 'አጣራ',
        'filters' => 'ማጣሪያዎች',
        'loading' => 'በመጫን ላይ…',
        'no_results' => 'ምንም የተገኘ መረጃ የለም',
        'next' => 'ቀጣይ',
        'page' => 'ገጽ',
        'pagination' => 'ገጾች',
        'previous' => 'ቀዳሚ',
        'pagination_range' => ':from–:to ከ:total',
        'remove' => 'አስወግድ',
        'replace' => 'ተካ',
        'required' => 'አስገዳጅ',
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
        'file_max_size' => 'ከፍተኛው የፋይል መጠን :size ሜባ',
    ],

    // Button labels that get the matching icon in the kit's Button (shell/Components/ui/actionIcons.tsx).
    'actions' => [
        'create' => 'ፍጠር',
        'add' => 'አክል',
        'save' => 'አስቀምጥ',
        'update' => 'አዘምን',
        'submit' => 'አቅርብ',
        'apply' => 'ተግብር',
        'approve' => 'አጽድቅ',
        'close' => 'ዝጋ',
        'reject' => 'ውድቅ አድርግ',
        'edit' => 'አስተካክል',
        'delete' => 'ሰርዝ',
        'retry' => 'በድጋሚ ሞክር',
        'view' => 'ተመልከት',
        'details' => 'ዝርዝር መረጃ',
        'export' => 'ላክ (Export)',
        'download' => 'አውርድ',
        'upload' => 'ጫን (Upload)',
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
        'permit_processing' => 'የፈቃድ አገልግሎት ሂደት',
        'payments_reports' => 'ክፍያዎችና ሪፖርቶች',
        'front_desk' => 'የደንበኞች መስተንግዶ',
        'access' => 'የተጠቃሚዎች አስተዳደርና ኦዲት',
        'configuration' => 'የሥርዓት ውቅር',
    ],

    'account' => [
        'menu' => 'የተጠቃሚ መለያ ምናሌ',
        'signed_in_as' => 'የገቡበት መለያ',
        'profile_settings' => 'የግል መረጃ ማስተካከያ',
        'sign_out' => 'ከሥርዓቱ ውጣ',
    ],

    'appearance' => [
        'title' => 'ገጽታ',
        'mode' => 'የገጽታ ቀለም',
        'system' => 'እንደ መሣሪያው ምርጫ',
        'light' => 'ብሩህ',
        'dark' => 'ጨለማ',
        'interface_style' => 'የገጽታ ዘይቤ',
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
        'empty' => 'ምንም አዲስ ማሳወቂያ የለም',
        'empty_hint' => 'ስለ ፈቃዶችዎ መረጃ በኢሜይልና በአጭር የጽሑፍ መልዕክት (SMS) ይላካል።',
    ],

    'breadcrumbs' => [
        'label' => 'የገጽ አቅጣጫ',
        'create' => 'ፍጠር',
        'edit' => 'አስተካክል',
        'details' => 'ዝርዝር',
    ],
];
