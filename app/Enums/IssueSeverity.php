<?php

namespace App\Enums;

/**
 * What an import issue meant for its row: the row was not imported, or it
 * was imported and the issue is a warning about one of its values.
 */
enum IssueSeverity: string
{
    case Skipped = 'skipped';
    case Warning = 'warning';
}
