<?php

namespace App\Services\Deploy;

/**
 * Thrown when the operator cancels a running deploy. Caught by the worker
 * to record a `cancelled` state (partial progress is kept — re-running
 * resumes incrementally).
 */
class DeployCancelled extends DeployException
{
    //
}
