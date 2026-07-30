# docs/processing.md

## 1. Overview

The Telegram update processing system is built as an async pipeline, supporting multiple ingestion sources and
at-least-once delivery semantics.

Main incoming event sources:

- Poller (long polling Telegram API)
- Webhooks (HTTP inbound updates)
- (optional) internal system events

All sources normalize data to a single format:

- UpdateContext

---

## 2. Core Principle

The system operates on the model:

> At-least-once processing with idempotent execution

This means:

- a single event may be processed multiple times
- repeated processing is permissible and expected
- the system does NOT guarantee exactly-once execution

---

## 3. Ingestion Layer

### 3.1 Poller

Poller receives updates via the Telegram getUpdates API.

Responsibilities:

- fetch updates
- normalize to UpdateContext
- forward into processing pipeline

Poller does NOT:

- store execution state
- wait for completion of execution
- know the processing result

---

### 3.2 Webhooks

Webhook endpoint receives updates from Telegram push mode.

Responsibilities:

- validate request
- normalize to UpdateContext
- forward into processing pipeline

Webhook does NOT:

- execute business logic
- block the HTTP response until processing completes
- store execution state

---

### 3.3 Unified Ingestion Contract

All incoming events are normalized to:

- UpdateContext

and passed forward without distinction of source.

---

## 4. Processing Pipeline

Main pipeline:

UpdateContext → ProcessingDaemon → InboxRouter → Execution Strategy → Scheduler → Outbound Layer → Telegram API

---

## 5. Routing Layer (InboxRouter)

Router determines the processing strategy:

- DirectStrategy
- AsyncStrategy
- QueueStrategy (partitioned execution)

Router does NOT execute work, only makes a decision.

---

## 6. Execution Strategies

### 6.1 DirectStrategy

- immediate enqueue into scheduler
- no ordering guarantees

---

### 6.2 AsyncStrategy

- optional executionKey coordination
- task may be wrapped by coordinator
- enqueued into scheduler

executionKey is used only as an ordering hint.

---

### 6.3 QueueStrategy (Partitioned)

- context is sent to a partition scheduler
- uses Redis stream / partition queue
- ensures ordered processing per key

---

## 7. Ordering Model

executionKey / partitionKey:

- ensures sequential task execution within a key
- is NOT a state machine
- does NOT guarantee completion
- is NOT used as truth storage

Ordering is:

> scheduling constraint, not execution state

---

## 8. Scheduling Layer

Scheduler is responsible for:

- concurrency management (fibers / async tasks)
- execution queue
- backpressure control
- dispatching tasks to the execution layer

Scheduler does NOT:

- know about the Telegram API
- manage retry logic
- manage ordering state

---

## 9. Execution Layer (Outbound)

Outbound layer is responsible for:

- HTTP execution to Telegram API
- retry logic
- handling Telegram errors (429, network failures)
- floodwait adaptation
- response normalization

Outbound does NOT:

- serve as source of truth
- store workflow state
- make routing decisions

---

## 10. Fault Model

The system supports:

### Process crash

- incomplete tasks may be re-delivered
- repeated processing is permissible

### Duplicate execution

- permissible
- must be safe (idempotent handlers)

### Network failure

- retry via executor
- no global consistency guarantees

---

## 11. Deduplication Model

Deduplication operates at the level of:

- jobId
- optional compound keys (jobId + partitionKey)

Deduplication does NOT guarantee:

- completion state
- success state
- Telegram execution state

---

## 12. State Model

The system separates states:

### Does NOT STORE:

- completion of Telegram requests
- global execution state
- "delivered successfully" truth

### MAY STORE:

- dedup keys
- floodwait state (blockedUntil)
- ordering coordination state (executionKey locks)

---

## 13. Multiple Ingestion Sources

The system supports multiple concurrent ingestion sources:

- PollerDaemon
- WebhookReceiver
- (optional) internal producers

All sources are equal and:

- use a unified pipeline
- do not coordinate with each other
- may create duplicate events

---

## 14. Consistency Guarantees

The system guarantees:

- at-least-once delivery into pipeline
- eventual execution under normal conditions
- ordering within executionKey (best-effort / configured)

The system does NOT guarantee:

- exactly-once execution
- strict global ordering
- immediate completion visibility

---

## 15. Design Constraints

MUST:

- be resilient to duplicates
- survive process crashes
- respect retry_after (FloodWait)
- separate ingestion / routing / execution

MUST NOT:

- turn into a distributed workflow engine
- use Redis as a source of execution truth
- require global synchronization for correctness

---

## 16. Mental Model

System =

event ingestion → scheduling constraints → execution engine

NOT =

distributed transactional workflow system

---

## 17. Summary

- Poller and Webhooks are equal event sources
- at-least-once model is mandatory
- executionKey = ordering hint, not state
- Outbound = execution engine
- Redis/cache = coordination layer
- duplicates are a normal system state

---

## 18. Notes

This document was translated from the original Russian version.
