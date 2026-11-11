<?php

return [
    'title' => 'የቀድሞ መዝገብ ማስገቢያ',
    'intro' => 'ከቀድሞው የGoogle ፎርም ፍቃዶቹን ያስገቡ። "Form Responses 1"ን በCSV ያውርዱ፤ መጀመሪያ የሙከራ ሂደት አድርገው ችግሮቹን ይመልከቱ፤ ከዚያ ማስገቢያውን ያስኪዱ። እንደገና ማስኬድ ድግግሞሽ አይፈጥርም።',
    'fields' => [
        'file' => 'የCSV ፋይል',
        'dry_run' => 'የሙከራ ሂደት፦ ሁሉንም ያረጋግጡ፤ ችግሮቹን ያሳዩ፤ ምንም አያስገቡ',
    ],
    'submit' => 'ይጀምሩ',
    'file_hint' => 'Google Sheets፦ File → Download → Comma-separated values (.csv)። ቢበዛ 50 MB።',
    'batches' => 'ሂደቶች',
    'empty' => 'እስካሁን ምንም አልገባም።',
    'dry_run' => 'የሙከራ ሂደት',
    'download_issues' => 'ችግሮቹን ያውርዱ (CSV)',
    'status' => [
        'pending' => 'በወረፋ ላይ',
        'running' => 'በሂደት ላይ',
        'completed' => 'ተጠናቋል',
        'failed' => 'አልተሳካም',
    ],
    'totals' => [
        'rows' => 'ረድፎች',
        'imported' => 'የገቡ',
        'already_imported' => 'ቀድሞ የገቡ',
        'skipped' => 'የተዘለሉ',
        'warnings' => 'ማስጠንቀቂያዎች',
        'organizations_created' => 'አዲስ ተቋማት',
        'vehicles_created' => 'አዲስ ተሽከርካሪዎች',
        'payments_created' => 'ክፍያዎች',
        'not_blocking' => 'የተደራረቡ (የማያግዱ)',
    ],
    'started_by' => 'በ:name',
    'refresh' => 'ያድሱ',
    'messages' => [
        'queued' => 'ማስገቢያ #:id በወረፋ ላይ ነው። ሂደቱን ለመከታተል ገጹን ያድሱ።',
    ],
];
