<?php

namespace App\Exceptions;

use DomainException;

/**
 * Biznes qaydası pozulanda service qatı bunu atır (məs. "rezervasiyası olan xidmət silinə bilməz").
 * bootstrap/app.php onu mərkəzləşdirilmiş şəkildə istifadəçiyə mesaj kimi qaytarır —
 * controller-lərdə try/catch yazmağa ehtiyac qalmır.
 */
class BusinessRuleException extends DomainException
{
}
