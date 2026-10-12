<?php

namespace App\Services\Bible;

use RuntimeException;

/** The Bible provider could not be reached (offline, rate limited, unknown chapter). */
class BibleUnavailable extends RuntimeException {}
