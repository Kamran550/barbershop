<?php

namespace App\Exceptions;

/** Seçilən vaxt artıq boş deyil (başqası tutub, iş saatından kənardır, bloklanıb və s.). */
class SlotUnavailableException extends BusinessRuleException
{
    public function __construct(string $message = 'Bu vaxt artıq boş deyil. Zəhmət olmasa başqa saat seçin.')
    {
        parent::__construct($message);
    }
}
