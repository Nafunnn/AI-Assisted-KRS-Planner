<?php

namespace App\Enums;

enum KrsPlanItemStatus: string
{
    case Active = 'active';
    case ScheduleChanged = 'schedule_changed';
    case SectionRemoved = 'section_removed';
}
