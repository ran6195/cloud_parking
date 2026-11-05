<?php

namespace App\Services\Engine;

use RuntimeException;

/**
 * Il motore non risponde, non è configurato o risponde con un errore del server.
 */
class EngineUnavailable extends RuntimeException {}
