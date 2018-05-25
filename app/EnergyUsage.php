<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EnergyUsage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'energy_usage';


        // Validate the request...

        protected $connection = 'mysql';
        protected $primaryKey = 'id';
        protected $fillable = array(
        'kwh_used',
        'cost',
        'high_temp',
        'low_temp',
        'average_temp',
        'date',
    );

}
