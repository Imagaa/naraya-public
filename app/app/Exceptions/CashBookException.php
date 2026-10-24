<?php

namespace App\Exceptions;

use RuntimeException;

/** A cash book rule was broken; its message is shown to admin as-is. */
class CashBookException extends RuntimeException {}
