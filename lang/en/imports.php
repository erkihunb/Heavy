<?php

return [
    'title' => 'Legacy import',
    'intro' => 'Import the permits from the old Google Form. Download "Form Responses 1" as CSV, run a dry run first and review its issues, then run the import. Running it again never creates duplicates.',
    'fields' => [
        'file' => 'CSV file',
        'dry_run' => 'Dry run: check everything and report the issues, but import nothing',
    ],
    'submit' => 'Start',
    'file_hint' => 'Google Sheets: File → Download → Comma-separated values (.csv). At most 50 MB.',
    'batches' => 'Runs',
    'empty' => 'No imports yet.',
    'dry_run' => 'Dry run',
    'download_issues' => 'Download issues (CSV)',
    'status' => [
        'pending' => 'Queued',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'totals' => [
        'rows' => 'Rows',
        'imported' => 'Imported',
        'already_imported' => 'Already imported',
        'skipped' => 'Skipped',
        'warnings' => 'Warnings',
        'organizations_created' => 'New organisations',
        'vehicles_created' => 'New vehicles',
        'payments_created' => 'Payments',
        'not_blocking' => 'Overlaps (not reserving)',
    ],
    'started_by' => 'by :name',
    'refresh' => 'Refresh',
    'messages' => [
        'queued' => 'Import #:id is queued. Refresh this page to follow it.',
    ],
];
