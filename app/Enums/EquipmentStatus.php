<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Available = 'available';
    case Maintenance = 'maintenance';
    case Broken = 'broken';
}
