<?php

namespace App\Observers;

use App\Event;
use Carbon\Carbon;

class RecurrenceObserver
{
    /**
     * Handle the event "created" event.
     *
     * @param  \App\Event  $event
     * @return void
     */
    public static function created(Event $event)
    {
        if(!$event->event()->exists())
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
            $startTime = Carbon::parse($event->start_time);
            $endTime = Carbon::parse($event->end_time);
            $recurrence = $recurrences[$event->recurrence] ?? null;

            if($recurrence)
                for($i = 0; $i < $recurrence['times']; $i++)
                {
                    $startTime->{$recurrence['function']}();
                    $endTime->{$recurrence['function']}();
                    $event->events()->create([
                        'name'          => $event->name,
                        'start_time'    => $startTime,
                        'end_time'      => $endTime,
                        'recurrence'    => $event->recurrence,
                    ]);
                }
        }
    }

    /**
     * Handle the event "updated" event.
     *
     * @param  \App\Event  $event
     * @return void
     */
    public function updated(Event $event)
    {
        if($event->events()->exists() || $event->event)
        {
            $startTime = Carbon::parse($event->getOriginal('start_time'))->diffInSeconds($event->start_time, false);
            $endTime = Carbon::parse($event->getOriginal('end_time'))->diffInSeconds($event->end_time, false);
            if($event->event)
                $childEvents = $event->event->events()->whereDate('start_time', '>', $event->getOriginal('start_time'))->get();
            else
                $childEvents = $event->events;

            foreach($childEvents as $childEvent)
            {
                if($startTime)
                    $childEvent->start_time = Carbon::parse($childEvent->start_time)->addSeconds($startTime);
                if($endTime)
                    $childEvent->end_time = Carbon::parse($childEvent->end_time)->addSeconds($endTime);
                if($event->isDirty('name') && $childEvent->name == $event->getOriginal('name'))
                    $childEvent->name = $event->name;
                $childEvent->saveQuietly();
            }
        }

        if($event->isDirty('recurrence') && $event->recurrence != 'none')
            self::created($event);
    }

    /**
     * Handle the event "deleted" event.
     *
     * @param  \App\Event  $event
     * @return void
     */
    public function deleted(Event $event)
    {
        if($event->events()->exists())
            $events = $event->events()->pluck('id');
        else if($event->event)
            $events = $event->event->events()->whereDate('start_time', '>', $event->start_time)->pluck('id');
        else
            $events = [];
        
            Event::whereIn('id', $events)->delete();
    }
}
