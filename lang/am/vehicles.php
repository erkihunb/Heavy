<?php

return [
    'title' => 'ተሽከርካሪዎች',
    'register' => 'ተሽከርካሪ ይመዝግቡ',
    'register_title' => 'ተሽከርካሪ ይመዝግቡ',
    'fields' => [
        'plate' => 'የሰሌዳ ቁጥር',
        'region' => 'ክልል',
        'code' => 'ኮድ',
        'number' => 'ቁጥር',
        'vehicle_type' => 'የተሽከርካሪ ዓይነት',
        'owner_name' => 'የተሽከርካሪ ባለንብረት',
    ],
    'plate_hint' => 'ክልሉን ይምረጡ፣ ከዚያ በሰሌዳው ላይ እንደተጻፈው ኮዱን እና ቁጥሩን ያስገቡ።',
    'plate_preview' => 'ሰሌዳው የሚቀመጥበት መልክ',
    'choose_type' => 'ዓይነት ይምረጡ',
    'actions' => [
        'save' => 'ተሽከርካሪውን ያስቀምጡ',
        'cancel' => 'ይቅር',
    ],
    'registered_message' => 'ተሽከርካሪው ተመዝግቧል።',
    'errors' => [
        'plate_invalid' => 'ሰሌዳውን በክልል፣ በኮድ እና በቁጥር ያስገቡ፤ ለምሳሌ አአ-03-A45301።',
        'plate_duplicate_own' => 'ይህ ተሽከርካሪ አስቀድሞ በመዝገብዎ ውስጥ አለ።',
        'plate_duplicate_other' => 'ይህ የሰሌዳ ቁጥር አስቀድሞ በሌላ ተቋም ተመዝግቧል። ስህተት ነው ብለው ካመኑ ባለስልጣኑን ያነጋግሩ።',
    ],
];
