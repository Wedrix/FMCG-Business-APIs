<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static )
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class Role extends Enum 
{
    const Admin = 0;
    const Member = 1;
    
}
