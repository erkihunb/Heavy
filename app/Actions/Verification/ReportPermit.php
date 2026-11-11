<?php

namespace App\Actions\Verification;

use App\Models\Permit;
use App\Models\PermitReport;
use App\Models\User;

class ReportPermit
{
    /**
     * A traffic officer records a violation or a problem seen at the
     * roadside. The report is kept with the permit for the Authority.
     */
    public function handle(Permit $permit, User $officer, string $note): PermitReport
    {
        $report = new PermitReport(['note' => trim($note)]);
        $report->permit_id = $permit->id;
        $report->reported_by = $officer->id;
        $report->save();

        return $report;
    }
}
