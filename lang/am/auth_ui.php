<?php

/*
 * Text of the sign-in and registration screens (sent to the frontend).
 * Server-side authentication messages live in auth.php.
 */

return [
    'email_or_username' => 'ኢሜይል ወይም የተጠቃሚ ስም',
    'email' => 'የኢሜይል አድራሻ',
    'password' => 'የይለፍ ቃል',
    /*
     * ከመግቢያ ቅጹ አጠገብ ያለው ሥዕል ሥርዓቱን ይሰይማል፤ በሁለቱም ቋንቋ አንድ ዓይነት ነው።
     */
    'login' => [
        'title' => 'ወደ መለያዎ ይግቡ',
        'description' => 'ለመግባት ኢሜይልዎን እና የይለፍ ቃልዎን ያስገቡ',
        'forgot_password' => 'የይለፍ ቃልዎን ረሱ?',
        'remember' => 'አስታውሰኝ',
        'submit' => 'ይግቡ',
    ],
    'forgot' => [
        'title' => 'የይለፍ ቃልዎን ረሱ',
        'description' => 'የኢሜይል አድራሻዎን ያስገቡ፤ አዲስ የይለፍ ቃል ለማዘጋጀት የሚያስችል አገናኝ እንልክልዎታለን።',
        'submit' => 'የመቀየሪያ አገናኝ በኢሜይል ይላክ',
        'back_prefix' => 'ወይም ይመለሱ ወደ',
        'back_link' => 'መግቢያ',
    ],
    'reset' => [
        'title' => 'አዲስ የይለፍ ቃል ያዘጋጁ',
        'description' => 'ለመለያዎ የሚሆነውን አዲስ የይለፍ ቃል ከታች ያስገቡ።',
        'new_password' => 'አዲስ የይለፍ ቃል',
        'confirm_password' => 'አዲሱን የይለፍ ቃል ያረጋግጡ',
        'submit' => 'አዲሱን የይለፍ ቃል ያስቀምጡ',
    ],
    'first_password' => [
        'title' => 'የራስዎን የይለፍ ቃል ይምረጡ',
        'description' => 'ያስገቡት ነባሪውን የይለፍ ቃል ነው። ለመቀጠል የራስዎን የይለፍ ቃል ይምረጡ፤ ከዚያ በእሱ ይገባሉ።',
        'submit' => 'የይለፍ ቃሌን አስቀምጥ',
        'back' => 'ወደ መግቢያ ይመለሱ',
        'done' => 'የይለፍ ቃልዎ ተቀምጧል። አሁን በእሱ ይግቡ።',
    ],
    'confirm' => [
        'title' => 'የይለፍ ቃልዎን ያረጋግጡ',
        'description' => 'ይህ የሥርዓቱ ክፍል የተጠበቀ ነው። ለመቀጠል የይለፍ ቃልዎን እንደገና ያስገቡ።',
        'submit' => 'የይለፍ ቃል ያረጋግጡ',
    ],
    'verify' => [
        'title' => 'የኢሜይል አድራሻዎን ያረጋግጡ',
        'description' => 'ወደ ኢሜይል አድራሻዎ የላክነውን አገናኝ ከፍተው ያረጋግጡ።',
        'sent' => 'አዲስ አገናኝ ወደ ኢሜይል አድራሻዎ ተልኳል።',
        'resend' => 'አገናኙን እንደገና ይላክ',
        'logout' => 'ይውጡ',
    ],
    'two_factor' => [
        'title' => 'የማረጋገጫ ኮድ',
        'description' => 'ከማረጋገጫ መተግበሪያዎ ያገኙትን ኮድ ያስገቡ።',
        'recovery_title' => 'የመልሶ ማግኛ ኮድ',
        'recovery_description' => 'ባለሁለት ደረጃ ማረጋገጫ ሲዘጋጅ ካስቀመጧቸው የመልሶ ማግኛ ኮዶች አንዱን ያስገቡ።',
        'recovery_placeholder' => 'የመልሶ ማግኛ ኮድ',
        'use_recovery' => 'በምትኩ የመልሶ ማግኛ ኮድ ይጠቀሙ',
        'use_code' => 'በምትኩ የማረጋገጫ ኮድ ይጠቀሙ',
        'or' => 'ወይም ይችላሉ',
        'submit' => 'ይቀጥሉ',
    ],
];
