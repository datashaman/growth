<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every entry in the workspace notification catalogue.
 *
 * A catalogue event is something a recipient genuinely wants to know about
 * asynchronously — not every state change. Each subclass is one event: it
 * names the event, renders a human title/body, and points at the surface
 * the recipient would open. Storage and fan-out are uniform and live here.
 *
 * Fan-out is `database` (the bell inbox) + `broadcast` (live arrival). The
 * `mcp` channel (#42) appends to {@see via()} when the fork gains
 * out-of-band server-to-client delivery — no subclass needs reshaping.
 */
abstract class WorkspaceNotification extends Notification
{
    /**
     * Stable catalogue event name, e.g. `change_request.decided`.
     */
    abstract public function event(): string;

    /**
     * Short headline shown in the bell panel.
     */
    abstract public function title(): string;

    /**
     * One-line description of what happened.
     */
    abstract public function body(): string;

    /**
     * Webapp path the notification links to, or null when there is none.
     */
    abstract public function url(): ?string;

    /**
     * The artifact the event concerns, as a (type, id) pair.
     *
     * @return array{0:string,1:string}
     */
    abstract public function subject(): array;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * The stored payload — also the broadcast payload.
     *
     * @return array<string,mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$subjectType, $subjectId] = $this->subject();

        return [
            'event' => $this->event(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
