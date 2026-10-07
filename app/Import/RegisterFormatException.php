<?php

namespace App\Import;

use RuntimeException;

/** The file is not a tender register we can read (unreadable, or missing columns). */
final class RegisterFormatException extends RuntimeException {}
