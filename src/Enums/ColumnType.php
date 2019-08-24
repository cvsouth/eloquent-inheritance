<?php namespace Cvsouth\Entities\Enums;

abstract class ColumnType
{
    const STANDARD = 1;
    
    const REMOTE_REFERENCE = 2;
    
    const MUTUAL_REFERENCE = 3;

    public static function All()
    {
        $types =
        [
            self::STANDARD,
         
            self::REMOTE_REFERENCE,
        
            self::MUTUAL_REFERENCE,
        ];
        return $types;
    }
}
