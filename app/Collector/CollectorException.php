<?php

namespace App\Collector;

use RuntimeException;

/** A download or page-reading failure that makes one source fail for this run. */
class CollectorException extends RuntimeException {}
