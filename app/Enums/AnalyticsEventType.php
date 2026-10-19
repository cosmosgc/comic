<?php

namespace App\Enums;

/**
 * Known analytics event types. The public endpoint still accepts any
 * short string, so external clients are not broken; the app itself writes
 * and compares only these values.
 */
enum AnalyticsEventType: string
{
    case PageView = 'page_view';
    case Login = 'login';
}
