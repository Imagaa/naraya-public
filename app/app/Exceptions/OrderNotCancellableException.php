<?php

namespace App\Exceptions;

use RuntimeException;

/** Its message is shown to admin as-is. */
class OrderNotCancellableException extends RuntimeException {}
