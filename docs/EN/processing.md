# docs/processing.md

## 1. Overview

The Telegram update processing system is an asynchronous pipeline that supports multiple ingestion sources and follows
an at-least-once delivery model.

Incoming events may originate from:

- Poller (long polling via Telegram API)
- Webhooks (HTTP inbound updates)
- (optional) internal system events

All sources are normalized into a single format:

- UpdateContext

---

## 2. Core Principle

The system operates under the model:

> At-least-once processing with idempotent execution

This means:

- an event may be processed multiple times
- duplicate processing is expected and valid
- the system does NOT guarantee exactly-once execution

---

## 3. Ingestion Layer

### 3.1 Poller

The poller retrieves updates via the Telegram getUpdates API.

Responsibilities:

- fetch updates
- normalize them into UpdateContext
- forward them into the processing pipeline

The poller MUST NOT:

- store execution state
- wait for processing completion
- know execution results

---

### 3.2 Webhooks

The webhook endpoint receives updates via Telegram push delivery.

Responsibilities:

- validate incoming request
- normalize into UpdateContext
- forward into the processing pipeline

The webhook MUST NOT:

- execute business logic
- block HTTP response until processing completes
- store execution state

---

### 3.3 Unified Ingestion Contract

All incoming events are normalized into:

- UpdateContext

and passed further through the same pipeline regardless of source.

---

## 4. Processing Pipeline

The main processing pipeline is:

UpdateContext → ProcessingDaemon → InboxRouter → Execution Strategy → Scheduler → Outbound Layer → Telegram API

---

## 5. Routing Layer (InboxRouter)

The router selects the execution strategy:

- DirectStrategy
- AsyncStrategy
- QueueStrategy (partitioned execution)

The router does NOT execute tasks, it only makes routing decisions.

---

## 6. Execution Strategies

### 6.1 DirectStrategy

- immediately enqueues task into scheduler
- no ordering guarantees

---

### 6.2 AsyncStrategy

- optional coordination via executionKey
- task may be wrapped by a coordinator
- enqueued into scheduler

executionKey is used only as an ordering hint

---

### 6.3 QueueStrategy (Partitioned)

- context is sent to a partition scheduler
- uses Redis streams or partitioned queues
- ensures ordered processing per key

---

## 7. Ordering Model

executionKey / partitionKey:

- ensures sequential execution within a key
- is NOT a state machine
- does NOT guarantee completion
- is NOT a source of truth

Ordering is:

> a scheduling constraint, not an execution state

---

## 8. Scheduling Layer

The scheduler is responsible for:

- concurrency control (fibers / async tasks)
- execution queue management
- backpressure handling
- dispatching tasks to the execution layer

The scheduler does NOT:

- interact with Telegram API
- manage retry logic
- manage ordering state

---

## 9. Execution Layer (Outbound)

The outbound layer is responsible for:

- HTTP execution against Telegram API
- retry logic
- handling Telegram errors (e.g. 429, network failures)
- flood-wait adaptation
- response normalization

The outbound layer does NOT:

- act as a source of truth
- store workflow state
- make routing decisions

---

## 10. Fault Model

The system supports:

### Process crash

- unfinished tasks may be redelivered
- reprocessing is expected and safe

### Duplicate execution

- allowed
- must be idempotent

### Network failure

- handled via retry mechanisms
- no global consistency guarantees

---

## 11. Deduplication Model

Deduplication operates at:

- jobId
- optional compound keys (jobId + partitionKey)

Deduplication does NOT guarantee:

- execution completion
- success state
- Telegram-side state consistency

---

## 12. State Model

The system explicitly separates state responsibilities:

### NOT STORED

- Telegram execution completion state
- global execution truth
- "delivered successfully" guarantees

### MAY BE STORED

- deduplication keys
- flood-wait state (blockedUntil)
- ordering coordination state (executionKey locks)

---

## 13. Multiple Ingestion Sources

The system supports multiple concurrent ingestion sources:

- PollerDaemon
- WebhookReceiver
- (optional) internal producers

All sources are equal participants:

- use the same pipeline
- are not coordinated with each other
- may generate duplicates

---

## 14. Consistency Guarantees

The system guarantees:

- at-least-once delivery into the pipeline
- eventual execution under normal conditions
- ordering within executionKey (best-effort / configurable)

The system does NOT guarantee:

- exactly-once execution
- strict global ordering
- immediate completion visibility to ingestion layers

---

## 15. Design Constraints

MUST:

- tolerate duplicate events
- survive process crashes
- respect Telegram retry_after (FloodWait)
- separate ingestion, routing, and execution responsibilities

MUST NOT:

- evolve into a distributed workflow engine
- use Redis as a source of truth for execution state
- require global synchronization for correctness

---

## 16. Mental Model

The system should be understood as:

event ingestion → scheduling constraints → execution engine

NOT as:

a distributed transactional workflow system

---

## 17. Summary

- Poller and Webhooks are equal ingestion sources
- at-least-once processing is mandatory
- executionKey is an ordering hint, not state
- Outbound is an execution engine, not an orchestrator
- Redis/cache is coordination only, not truth
- duplicates are expected and safe
