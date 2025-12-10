# Glossary

Terminology used in the Queue package.

---

## Core Terms

### Job

A unit of work that can be executed asynchronously. Jobs extend the `Job` base class and implement a `handle()` method.

### Queue

A named channel for organizing jobs. Jobs are processed in order within their queue. Examples: `default`, `emails`, `reports`.

### Payload

Data passed to a job when dispatched. The payload is available in the `handle()` method as an array.

### Driver

The backend storage mechanism for the queue. Determines how jobs are stored and retrieved (memory, database).

---

## Job States

### Pending

Job is queued and ready to be processed.

### Delayed

Job is waiting for its availability time before becoming pending.

### Processing

Job is currently being executed by a worker.

### Completed

Job finished successfully.

### Failed

Job failed after exhausting all retry attempts and was moved to the dead letter queue.

---

## Processing Terms

### Worker

A process that retrieves and executes jobs from the queue.

### Batch Processing

Processing multiple jobs in a single worker cycle.

### Synchronous Execution

Running a job immediately in the current process, bypassing the queue.

---

## Retry Terms

### Retry Attempt

A subsequent execution of a job after a failure.

### Max Attempts

The maximum number of times a job will be tried before being marked as failed.

### Backoff Strategy

The algorithm used to calculate delay between retry attempts.

### Exponential Backoff

Delay doubles with each attempt (60s → 120s → 240s).

### Linear Backoff

Delay increases by a fixed amount each attempt (60s → 120s → 180s).

### Fixed Backoff

Same delay for all retry attempts.

### Jitter

Random variation added to retry delays to prevent thundering herd problems.

---

## Failure Handling

### Dead Letter Queue (DLQ)

Storage for jobs that have permanently failed. Also called `failed_jobs` table.

### Retry History

Log of previous failure attempts for a job.

### Stack Trace

Full error trace captured when a job fails.

---

## Priority Terms

### Priority

Numeric value (0-10) indicating job importance. Lower numbers are processed first.

### High Priority

Jobs with priority 0-2, processed before others.

### Low Priority

Jobs with priority 8-10, processed last.

---

## Configuration Terms

### Queue Driver

Configuration setting that determines the storage backend (memory, mysql, pgsql, sqlite).

### Job Timeout

Maximum time a job can run before being considered stuck.

### Stuck Job

A job that has been processing longer than the timeout threshold.

---

## Classes

### Job

Base class for all queue jobs.

### QueueManager

Central coordinator for job enqueueing and processing.

### PendingDispatch

Fluent builder for configuring job dispatch.

### JobExecutor

Responsible for executing job handle() methods.

### RetryStrategy

Calculates retry delays using various backoff algorithms.

### QueueLogger

Logs job execution and metrics.

### InMemoryQueueDriver

Driver that stores jobs in PHP arrays (development).

### AbstractDatabaseQueueDriver

Base class for database-backed drivers (production).

---

## Interfaces

### JobInterface

Contract that all jobs must implement.

### QueueDriverInterface

Contract for queue storage backends.

### JobDispatcherInterface

Contract for adding jobs to queues.

### JobProcessorInterface

Contract for processing jobs.

### JobExecutorInterface

Contract for executing job logic.

### QueueLoggerInterface

Contract for logging job activity.

---

## Tables

### jobs

Main queue table storing pending and processing jobs.

### failed_jobs

Dead letter queue for permanently failed jobs.

### job_logs

Execution logs with metrics and error details.

---

## CLI Commands

### queue:work

Process jobs from the queue.

### queue:failed

View failed jobs.

### queue:retry

Retry a failed job.

### queue:flush-failed

Delete all failed jobs.

### queue:stats

View queue statistics.

### queue:maintain

Run maintenance tasks (cleanup, release stuck jobs).

---

## Next Steps

- [Core Concepts](concepts.md) - Detailed explanations
- [API Reference](api-reference.md) - Full API documentation
