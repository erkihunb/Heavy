<?php

namespace App\Domain\Legacy;

use RuntimeException;

/**
 * A value in the legacy sheet could not be read. The message is written into
 * the import issues for a person to review.
 */
final class InvalidLegacyValue extends RuntimeException {}
