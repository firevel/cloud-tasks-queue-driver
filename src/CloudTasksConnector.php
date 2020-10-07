<?php

namespace Firevel\CloudTasksQueueDriver;

use Illuminate\Queue\Connectors\ConnectorInterface;

class CloudTasksConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new CloudTasksQueue($config);
    }
}
