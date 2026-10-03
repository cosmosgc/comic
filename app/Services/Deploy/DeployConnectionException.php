<?php

namespace App\Services\Deploy;

/**
 * Transport-level failure (timeout, dropped connection, TLS error).
 * Retried once on a fresh connection; FTP reply errors (e.g. a missing
 * file) are NOT connection failures and bubble up immediately.
 */
class DeployConnectionException extends DeployException
{
    //
}
