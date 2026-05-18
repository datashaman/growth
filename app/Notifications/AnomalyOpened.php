<?php

namespace App\Notifications;

use App\Models\Anomaly;

/**
 * Catalogue event `anomaly.opened`.
 *
 * Payload: the newly created anomaly and its severity.
 * Recipients: every member of the project's workspace, minus the actor.
 * Emitted when UpsertAnomaly creates an anomaly (anomalies open on creation).
 */
class AnomalyOpened extends WorkspaceNotification
{
    public function __construct(private readonly Anomaly $anomaly) {}

    public function event(): string
    {
        return 'anomaly.opened';
    }

    public function title(): string
    {
        return 'Anomaly opened';
    }

    public function body(): string
    {
        return sprintf('%s severity: “%s”.', ucfirst($this->anomaly->severity), $this->anomaly->summary);
    }

    public function url(): ?string
    {
        return route('anomalies.show', $this->anomaly->id, false);
    }

    public function subject(): array
    {
        return ['anomaly', $this->anomaly->id];
    }
}
