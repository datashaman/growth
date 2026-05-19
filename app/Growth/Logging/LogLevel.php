<?php

namespace App\Growth\Logging;

/**
 * Severity of a structured log record, mirroring the eight syslog levels the
 * MCP logging utility recognises.
 *
 * The backing string is the wire value an MCP client negotiates through
 * `logging/setLevel` and receives on a `notifications/message`; everywhere
 * else this enum is just a transport-agnostic severity for {@see LogReporter}.
 */
enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';
}
