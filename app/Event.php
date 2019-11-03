<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    public $table = 'events';

    protected $dates = [
        'end_time',
        'start_time',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    const RECURRENCE_RADIO = [
        'none'    => 'None',
        'daily'   => 'Daily',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
    ];

    protected $fillable = [
        'name',
        'end_time',
        'event_id',
        'start_time',
        'recurrence',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'event_id', 'id');
    }

    public function getStartTimeAttribute($value)
    {
        return $value ? Carbon::createFromFormat('Y-m-d H:i:s', $value)->format(config('panel.date_format') . ' ' . config('panel.time_format')) : null;
    }

    public function setStartTimeAttribute($value)
    {
        $this->attributes['start_time'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }

    public function getEndTimeAttribute($value)
    {
        return $value ? Carbon::createFromFormat('Y-m-d H:i:s', $value)->format(config('panel.date_format') . ' ' . config('panel.time_format')) : null;
    }

    public function setEndTimeAttribute($value)
    {
        $this->attributes['end_time'] = $value ? Carbon::createFromFormat(config('panel.date_format') . ' ' . config('panel.time_format'), $value)->format('Y-m-d H:i:s') : null;
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function createRecurringEvents()
    {
        $recurrences = [
            'daily'     => [
                'times'     => 365,
                'function'  => 'addDay'
            ],
            'weekly'    => [
                'times'     => 52,
                'function'  => 'addWeek'
            ],
            'monthly'    => [
                'times'     => 12,
                'function'  => 'addMonth'
            ]
        ];
        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);
        $recurrence = $recurrences[$this->recurrence] ?? null;

        if($recurrence)
            for($i = 0; $i < $recurrence['times']; $i++)
            {
                $startTime->{$recurrence['function']}();
                $endTime->{$recurrence['function']}();
                $this->events()->create([
                    'name'          => $this->name,
                    'start_time'    => $startTime,
                    'end_time'      => $endTime,
                    'recurrence'    => $this->recurrence,
                ]);
            }
    }
}
