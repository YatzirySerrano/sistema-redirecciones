<?php

namespace App\Services\Qr;

use RuntimeException;

/**
 * El QR no pudo generarse con las opciones solicitadas (por ejemplo, un
 * logotipo dañado). El mensaje es apto para mostrarse al usuario.
 */
class ErrorGeneracionQr extends RuntimeException {}
