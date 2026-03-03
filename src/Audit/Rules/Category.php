<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

enum Category: string
{
    case LOCKING = 'locking';
    case HANG = 'hang';
    case BOUNDS = 'bounds';
    case TIMEOUTS = 'timeouts';
    case ERRORS = 'errors';
    case OBSERVABILITY = 'observability';
    case CONFIG = 'config';
    case DEPS = 'deps';
    case DOCS = 'docs';
    case SECURITY_BASELINE = 'security_baseline';
}
