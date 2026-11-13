<?php

/*
 * Text of the sign-in and registration screens (sent to the frontend).
 * Server-side authentication messages live in auth.php.
 */

return [
    'email' => 'የኢሜይል አድራሻ',
    'password' => 'የይለፍ ቃል',
    'panel' => [
        'eyebrow' => 'የአዲስ አበባ ከተማ መንገዶች ባለሥልጣን',
        'title' => 'ለከባድ ተሽከርካሪዎች የመንቀሳቀሻ ፈቃድ፣ ከጥያቄ እስከ ፈቃድ አሰጣጥ',
        'intro' => 'ከባድ ተሽከርካሪዎች ያለ ፈቃድ በተከለከሉ ሰዓታት መንቀሳቀስ አይችሉም። ባለሥልጣኑ እያንዳንዱን ጥያቄ በቅርንጫፍ ወይም በዋና መሥሪያ ቤት ይመዘግባል፣ ይወስናል፣ ክፍያውን ይቀበላል እና ፈቃዱን ይሰጣል።',
        'steps' => [
            'apply' => [
                'title' => 'በቢሮ ያመልክቱ',
                'body' => 'የጥያቄ ደብዳቤ ወደ ቅርንጫፍ ይዘው ይምጡ። ሠራተኞች ድርጅቱን፣ ተሽከርካሪውን እና ቀኖቹን ይመዘግባሉ።',
            ],
            'decide' => [
                'title' => 'ግምገማና ክፍያ',
                'body' => 'ገምጋሚ ጥያቄውን ይመረምራል፣ አጽዳቂ ይወስናል፣ ክፍያውም በካውንተር ይፈጸማል።',
            ],
            'verify' => [
                'title' => 'ተሰጥቶ ይረጋገጣል',
                'body' => 'ፈቃዱ የትራፊክ ፖሊስ በመንገድ ላይ ሊቃኘውና ሊያረጋግጠው የሚችል ኮድ ይዟል።',
            ],
        ],
        'no_account' => 'መለያዎች የሚከፈቱት በቢሮ ነው፤ እዚህ ማንም አይመዘገብም። ሠራተኞች ባለሥልጣኑ በሰጣቸው መለያ ይግቡ።',
    ],
    'login' => [
        'title' => 'ወደ መለያዎ ይግቡ',
        'description' => 'ለመግባት ኢሜይልዎን እና የይለፍ ቃልዎን ያስገቡ',
        'forgot_password' => 'የይለፍ ቃልዎን ረሱ?',
        'remember' => 'አስታውሰኝ',
        'submit' => 'ይግቡ',
        'no_account' => 'መለያ የለዎትም?',
        'sign_up' => 'ይመዝገቡ',
    ],
    'register' => [
        'title' => 'መለያ ይፍጠሩ',
        'description' => 'መለያዎን ለመፍጠር ዝርዝር መረጃዎን ያስገቡ',
        'name' => 'ስም',
        'name_placeholder' => 'ሙሉ ስም',
        'confirm_password' => 'የይለፍ ቃልዎን ያረጋግጡ',
        'submit' => 'መለያ ይፍጠሩ',
        'have_account' => 'መለያ አለዎት?',
        'log_in' => 'ይግቡ',
    ],
];
