<?php

return [
    'title' => 'ዳሽቦርድ',
    'greeting' => 'እንኳን ደህና መጡ፣ :name',
    'intro' => 'ዛሬ :date ነው።',
    'kpis' => [
        'vehicles' => 'የተመዘገቡ ተሽከርካሪዎች',
        'in_force' => 'በሥራ ላይ ያሉ ፍቃዶች',
        'in_progress' => 'በግምገማ ላይ ያሉ ማመልከቻዎች',
        'awaiting_payment' => 'ክፍያ የሚጠብቁ',
        'waiting_review' => 'ግምገማ የሚጠብቁ',
        'payments_to_verify' => 'ማረጋገጫ የሚጠብቁ ደረሰኞች',
        'expiring' => 'በ7 ቀናት የሚያበቁ',
        'scans_today' => 'የዛሬ ማረጋገጫዎች',
        'issued_month' => 'በ:month የተሰጡ',
        'revenue_month' => 'በ:month የተገኘ ገቢ',
    ],
    'attention' => [
        'title' => 'ትኩረት የሚሹ',
        'none' => 'ትኩረትዎን የሚሻ ነገር የለም።',
        'open' => 'ይክፈቱ',
        'items' => [
            'email_unverified' => [
                'label' => 'የኢሜይል አድራሻዎን ያረጋግጡ',
                'description' => 'የላክንልዎትን ሊንክ ይጠቀሙ፣ ወይም ከቅንብሮችዎ እንደገና ይላኩ።',
            ],
            'two_factor_off' => [
                'label' => 'ባለሁለት ደረጃ ማረጋገጫን ያብሩ',
                'description' => 'ከስልክዎ የሚመጣ የአንድ ጊዜ ኮድ መለያዎን ይጠብቃል።',
            ],
            'no_organization' => [
                'label' => 'የተቋምዎን መገለጫ ይፍጠሩ',
                'description' => 'ተሽከርካሪዎችን ከመመዝገብዎ በፊት መገለጫ ያስፈልግዎታል።',
            ],
            'no_vehicles' => [
                'label' => 'ተሽከርካሪዎችዎን ያስመዝግቡ',
                'description' => 'ፍቃድ የሚሰጠው ለተመዘገቡ ተሽከርካሪዎች ነው።',
            ],
            'changes_requested' => [
                'label' => 'ለማስተካከያ የተመለሱ ማመልከቻዎች',
                'description' => 'አስተካክለው እንደገና ያቅርቡ።',
            ],
            'awaiting_payment' => [
                'label' => 'የጸደቁና ክፍያ የሚጠብቁ',
                'description' => 'ክፍያውን ፈጽመው ደረሰኙን ይጫኑ።',
            ],
            'waiting_review' => [
                'label' => 'ግምገማ የሚጠብቁ ማመልከቻዎች',
                'description' => 'በግምገማ ወረፋው ውስጥ የቆየው መጀመሪያ።',
            ],
            'payments_to_verify' => [
                'label' => 'ማረጋገጫ የሚጠብቁ ደረሰኞች',
                'description' => 'ከባንክ መግለጫው ጋር ያመሳክሩ።',
            ],
            'expiring' => [
                'label' => 'በሚቀጥሉት 7 ቀናት የሚያበቁ ፍቃዶች',
                'description' => 'ተሽከርካሪዎቹ አዲስ ፍቃድ ሊያስፈልጋቸው ይችላል።',
            ],
        ],
    ],
    'distribution' => [
        'title' => 'ፍቃዶች በሁኔታ',
        'total' => ':count ፍቃዶች',
        'empty' => 'እስካሁን ፍቃድ የለም።',
    ],
    'activity' => [
        'title' => 'የቅርብ ጊዜ እንቅስቃሴ',
        'empty' => 'እስካሁን የሆነ ነገር የለም።',
        'created' => 'ማመልከቻ ተጀመረ',
        'reissued' => 'በአዲስ QR ኮድ በድጋሚ ተሰጠ',
        'when' => 'መቼ',
        'what' => 'የሆነው',
        'permit' => 'ፍቃድ',
        'organization' => 'ተቋም',
        'by' => 'ያደረገው',
    ],
    'recent' => [
        'title' => 'የቅርብ ጊዜ ማመልከቻዎቼ',
        'empty' => 'እስካሁን ለፍቃድ አላመለከቱም።',
        'all' => 'ሁሉም ማመልከቻዎች',
    ],
    'actions' => [
        'apply' => 'ለፍቃድ ያመልክቱ',
        'register_vehicle' => 'ተሽከርካሪ ያስመዝግቡ',
        'queue' => 'የግምገማ ወረፋ',
        'payments' => 'ክፍያዎችን ያረጋግጡ',
        'scan' => 'ፍቃድ ያረጋግጡ',
        'reports' => 'ሪፖርቶች',
    ],
];
