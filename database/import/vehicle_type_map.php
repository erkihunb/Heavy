<?php

/*
|--------------------------------------------------------------------------
| Legacy vehicle type spellings → vehicle type code
|--------------------------------------------------------------------------
|
| REVIEWED MAPPING for the legacy import (spec §13). Keys are compared after
| App\Domain\Legacy\LegacyValues::normalizeText(): spaces, underscores and
| punctuation removed. Values are codes in `vehicle_types`. A spelling that is
| not listed is imported with the type "legacy_unmapped" and reported as an
| issue, so it can be reviewed and added here before the final import.
|
| Only the types named in the specification are mapped so far. Add the other
| spellings found in the real sheet after a dry run (the issues CSV lists them).
|
*/

return [
    // Cargo trucks
    'የጭነት' => 'cargo',
    'ጭነት' => 'cargo',
    'የጭነትተሽከርካሪ' => 'cargo',
    'የጭነትመኪና' => 'cargo',
    'cargo' => 'cargo',

    // Special mobile equipment (cranes, loaders …)
    'ልዩተንቀሳቃሽ' => 'special_mobile',
    'ልዩተንቀሳቃሸ' => 'special_mobile',
    'ልዩተንቀሳቃሽመሣሪያ' => 'special_mobile',
    'ልዩተንቀሳቃሽመሳሪያ' => 'special_mobile',
];
