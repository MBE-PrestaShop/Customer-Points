<?php

class pointsfideliteEntity extends ObjectModel
{
    public $id_pointsfidelite;
    public $id_customer;
    public $point;
    public $date_add;

    public static $definition = [
        'table' => 'pointsfidelite',
        'primary' => 'id_pointsfidelite',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'point' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'date_add' => ['type' => self::TYPE_DATE],
        ],
    ];
}
