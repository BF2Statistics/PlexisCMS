<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace System\Events;

/**
 * Manages event listeners and dispatching of events.
 *
 * The EventManager allows the registration of listeners for specific events,
 * the dispatching of events to these listeners, and the removal of listeners.
 */
class EventManager
{
    private static array $listeners = [];

    /**
     * Registers a listener for a specified event, with an optional priority.
     *
     * @param string $eventName The name of the event to listen for.
     * @param callable $listener The callback function to execute when the event is triggered.
     * @param int $priority The priority of the listener. Lower numbers indicate higher priority (loaded earlier). Default is 50.
     *
     * @return void
     */
    public static function Listen(string $eventName, callable $listener, int $priority = 50): void
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }

        self::$listeners[$eventName][] = [
            'callback' => $listener,
            'priority' => $priority
        ];

        // Sort by priority (higher priority first)
        usort(self::$listeners[$eventName], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Registers an event subscriber to listen for specific events.
     *
     * @param EventSubscriberInterface $subscriber The event subscriber that provides event and handler definitions.
     * @return void
     */
    public static function RegisterSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::GetSubscribedEvents() as $eventName => $params)
        {
            // $params = [class, method, priority]
            $class = $params[0];
            $method = $params[1];
            $priority = $params[2] ?? 50;

            // Convert to callable - for static methods
            $callable = [$class, $method];

            self::Listen($eventName, $callable, $priority);
        }
    }

    /**
     * Dispatches an event to all registered listeners for the specified event name.
     *
     * @param string $eventName The name of the event to dispatch.
     * @param Event $event The event object containing context and data for the event.
     * @return mixed The return value (if any) of the last listener called.
     */
    public static function Dispatch(string $eventName, Event $event): mixed
    {
        if (!self::HasListeners($eventName)) {
            return null;
        }

        $return = null;
        foreach (self::$listeners[$eventName] as $listener)
        {
            if ($event instanceof StoppableEventInterface)
            {
                if ($event->isPropagationStopped()) {
                    break;
                }
            }

            $return = call_user_func($listener['callback'], $event);
        }

        return $return;
    }

    /**
     * Removes a registered listener or all listeners for a specific event name.
     *
     * @param string $eventName The name of the event for which listeners should be removed.
     * @param callable|null $listener The specific listener to remove or null to remove all listeners for the event.
     * @return void
     */
    public static function Remove(string $eventName, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset(self::$listeners[$eventName]);
        }
        else
        {
            if (isset(self::$listeners[$eventName]))
            {
                self::$listeners[$eventName] = array_filter(
                    self::$listeners[$eventName],
                    fn($l) => $l['callback'] !== $listener
                );
            }
        }
    }

    /**
     * Determines whether there are any listeners registered for a given event.
     *
     * @param string $eventName The name of the event to check for listeners.
     *
     * @return bool True if there are listeners registered for the event, otherwise false.
     */
    public static function HasListeners(string $eventName): bool
    {
        return isset(self::$listeners[$eventName]) && count(self::$listeners[$eventName]) > 0;
    }
}