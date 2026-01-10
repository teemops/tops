# Glossary

This glossary defines technical terms, jargon, and acronyms used throughout the practices documentation. Terms are organized alphabetically for easy reference.

## A

**A/B Testing**  
A method of comparing two versions of a feature or design to determine which performs better. Users are randomly assigned to version A or B, and metrics are compared to see which version achieves the desired outcome.

**Allowlist**  
A security practice where only explicitly approved items (IP addresses, file types, etc.) are permitted. The opposite of a blocklist/blacklist. More precise than "whitelist."

**APM (Application Performance Monitoring)**  
Tools and practices for monitoring application performance in real-time, including response times, throughput, error rates, and resource usage.

**Asynchronous**  
Operations that don't block or wait for completion. The caller can continue with other work while the operation runs in the background. Opposite of synchronous.

## B

**Blue-Green Deployment**  
A deployment strategy where two identical production environments (blue and green) exist. One serves traffic while the other is updated, then traffic is switched. Allows instant rollback by switching back.

**Bottleneck**  
A point in a system where performance is limited, causing slowdowns. Can be in code, database queries, network, or infrastructure. Should be identified through profiling and monitoring before optimizing.

## C

**C4 Notation**  
A simple diagramming notation for software architecture with four levels: Context (system and users), Container (applications and data stores), Component (components within containers), and Code (classes and functions).

**Canary Deployment**  
A deployment strategy where a new version is rolled out to a small subset of users first. If successful, it's gradually rolled out to more users. Allows early detection of issues.

**Chaos Engineering**  
The practice of intentionally introducing failures into a system to test its resilience and identify weaknesses. Helps ensure systems can handle real-world failures.

**CI/CD (Continuous Integration/Continuous Deployment)**  
CI: Automatically building and testing code when changes are committed. CD: Automatically deploying code that passes tests to production. Enables frequent, reliable releases.

**Circuit Breaker**  
A pattern that prevents cascading failures by stopping requests to a failing service. When failures exceed a threshold, the circuit "opens" and requests fail fast. After a timeout, it attempts to "close" again.

**Cognitive Load**  
The mental effort required to understand and use a system. Lower cognitive load means users can accomplish tasks more easily without excessive thinking or learning.

**Composition**  
Building complex functionality by combining simpler components, rather than using inheritance. Generally more flexible and maintainable than large class hierarchies.

**Connection Pooling**  
Reusing database connections instead of creating new ones for each request. Improves performance by avoiding the overhead of establishing connections repeatedly.

**Contract Testing**  
Testing that verifies the contract (interface) between services is maintained. Ensures that when one service changes, it doesn't break others that depend on it. Catches breaking changes early.

**Correlation ID**  
A unique identifier attached to a request that flows through all services handling that request. Enables tracing a request across distributed systems for debugging and monitoring.

**Coupling**  
The degree to which components depend on each other. Low coupling means components are independent and changes to one don't require changes to others. High coupling makes systems brittle.

**Cohesion**  
The degree to which elements within a component belong together and work toward a single purpose. High cohesion means a component has a clear, focused responsibility.

**CSAT (Customer Satisfaction Score)**  
A metric measuring customer satisfaction, typically on a scale (e.g., 1-5). Calculated as the percentage of satisfied customers (usually 4-5 on a 5-point scale).

**Cyclomatic Complexity**  
A measure of code complexity based on the number of decision points (if statements, loops, etc.). Higher complexity makes code harder to understand, test, and maintain.

## D

**Dead Letter Queue (DLQ)**  
A queue for messages that failed processing after multiple retry attempts. Allows investigation and reprocessing of failed messages without blocking the main processing flow.

**Denormalization**  
Intentionally adding redundant data to a database to improve query performance. Trade-off: faster reads but more complex writes and potential data inconsistency. Should only be done when there's a clear performance need.

**Design for Query Patterns, Not Theoretical Flexibility**  
Design your database schema based on how you actually query the data, not on theoretical future needs. For example, if you always query users by email, optimize for that pattern rather than creating a generic "search any field" structure you might never need.

**Distributed Tracing**  
Tracking a request as it flows through multiple services in a distributed system. Uses correlation IDs to create a trace showing the full path and timing of a request across services.

## E

**E2E Testing (End-to-End Testing)**  
Testing that verifies complete user workflows from start to finish, often across multiple systems. Tests the system as a user would experience it.

**ER Diagram (Entity-Relationship Diagram)**  
A visual representation of database structure showing tables (entities) and their relationships. Helps understand data models at a glance.

**Event Streams**  
A pattern where services publish events (things that happened) and other services subscribe to events they care about. Enables loose coupling between services.

**Eventual Consistency**  
A data consistency model where systems may temporarily have different data, but will eventually converge to the same state. Used in distributed systems where strong consistency would be too slow or complex.

**EXPLAIN / Query Plan**  
Database commands that show how a query will be executed, including which indexes are used and in what order. Essential for understanding and optimizing query performance.

**Exponential Backoff**  
A retry strategy where the wait time between retries increases exponentially (e.g., 1s, 2s, 4s, 8s). Prevents overwhelming a failing service while still attempting to recover.

## F

**Feature Flags**  
A technique for enabling/disabling features without deploying new code. Allows gradual rollouts, A/B testing, and instant feature toggles for emergency rollbacks.

## H

**Health Checks**  
Endpoints that report whether a service is healthy and ready to handle traffic. Used by load balancers to route traffic only to healthy instances. Liveness checks verify the service is running; readiness checks verify it can handle requests.

**Horizontal Scaling**  
Scaling by adding more instances of a service (scaling out). Generally preferred over vertical scaling because it's more flexible and cost-effective.

## I

**Idempotency**  
An operation that can be performed multiple times with the same result. Critical for retries and distributed systems where the same request might be processed multiple times.

**Idempotency Key**  
A unique identifier for an operation that ensures it's only processed once, even if the request is sent multiple times. Prevents duplicate processing of critical operations.

**Infrastructure as Code (IaC)**  
Managing infrastructure (servers, networks, etc.) through code and version control, rather than manual configuration. Enables reproducible, reviewable infrastructure changes.

**Integration Tests**  
Tests that verify multiple components work together correctly. Test behavior across boundaries (e.g., database, APIs) rather than isolated units.

## J

**Jitter**  
Random variation added to retry timing to prevent multiple clients from retrying simultaneously (thundering herd problem). Makes retry patterns more distributed.

**JSON (JavaScript Object Notation)**  
A lightweight data format commonly used for APIs and configuration. Human-readable and easy to parse.

## L

**Linter**  
A tool that analyzes code for potential errors, style violations, and best practices. Helps maintain code quality and consistency automatically.

**Liveness Check**  
A health check that verifies a service is running. If it fails, the service is considered dead and should be restarted.

## M

**Materialized Views**  
Pre-computed database views that store query results. Expensive to maintain but can dramatically speed up complex queries. Should only be used when queries are actually slow.

**MFA (Multi-Factor Authentication)**  
Authentication requiring multiple factors (something you know, something you have, something you are). More secure than password-only authentication.

**Microservices**  
An architectural pattern where an application is built as a collection of small, independent services. Each service has its own database and can be deployed independently. Should only be used when there's a clear need (team independence, different scaling needs).

**Mocks / Stubs**  
Test doubles used in testing. Mocks verify interactions; stubs provide predefined responses. Allow testing components in isolation without real dependencies.

**Modular Monolith**  
A monolith (single deployable application) organized into clear modules with defined boundaries. Simpler than microservices but allows future splitting if needed.

**MVP (Minimum Viable Product)**  
The smallest version of a product that delivers value to users. Focuses on core functionality to validate ideas quickly before adding features.

## N

**N+1 Query Problem**  
A performance issue where fetching a list of items triggers N additional queries (one per item). For example, loading 100 users triggers 100 additional queries to load their profiles. Should be solved with eager loading or joins.

**NPS (Net Promoter Score)**  
A metric measuring customer loyalty. Based on "How likely are you to recommend us?" (0-10 scale). Calculated as % promoters (9-10) minus % detractors (0-6).

**Normalization (3NF - Third Normal Form)**  
Organizing database data to eliminate redundancy and ensure data integrity. 3NF means data depends only on the primary key, not on other non-key attributes. Start here, denormalize only when needed.

## O

**OAuth**  
An authorization protocol that allows users to grant limited access to their resources without sharing passwords. Commonly used for "Sign in with Google/Facebook" functionality.

**Observability**  
The ability to understand what's happening inside a system through logs, metrics, and traces. Essential for debugging and monitoring production systems.

**ORM (Object-Relational Mapping)**  
A tool that maps database tables to programming language objects. Simplifies database access but can generate inefficient queries if not used carefully.

## P

**Parameterized Queries**  
Database queries where user input is passed as parameters rather than concatenated into the SQL string. Prevents SQL injection attacks and improves performance through query plan caching.

**Partitioning**  
Splitting a large database table into smaller physical pieces (partitions). Can improve query performance for very large tables, but adds complexity. Only use when you have actual scale problems.

**PII (Personally Identifiable Information)**  
Data that can identify a specific person (name, email, SSN, etc.). Requires special handling for privacy and security compliance.

**Premature Optimization**  
Optimizing code before identifying actual bottlenecks through profiling. Often results in complex, hard-to-maintain code that doesn't improve performance.

**Profiling**  
Analyzing code execution to identify bottlenecks and performance issues. Shows which functions consume the most time or resources. Essential before optimizing.

**Progressive Disclosure**  
A UX pattern where complex information or options are hidden initially and revealed as needed. Reduces cognitive load by showing only what's relevant at each step.

## R

**RBAC (Role-Based Access Control)**  
An authorization model where permissions are assigned to roles, and users are assigned roles. Simplifies permission management compared to assigning permissions directly to users.

**Read Replicas**  
Database copies that handle read queries, reducing load on the primary database. Useful for read-heavy workloads. Only add when you have actual read performance issues.

**Readiness Check**  
A health check that verifies a service is ready to handle requests (dependencies are available, etc.). Used by load balancers to route traffic.

**Refactoring**  
Improving code structure without changing functionality. Makes code more maintainable and easier to understand. Should be done continuously in small steps.

**RESTful**  
An API design style following REST (Representational State Transfer) principles. Uses HTTP methods (GET, POST, PUT, DELETE) and standard status codes. Simple and widely understood.

**RPO (Recovery Point Objective)**  
The maximum acceptable amount of data loss measured in time. Determines backup frequency. For example, RPO of 1 hour means backups must be at most 1 hour apart.

**RTO (Recovery Time Objective)**  
The maximum acceptable downtime after a failure. Determines how quickly systems must be restored. For example, RTO of 4 hours means systems must be restored within 4 hours.

## S

**SAST (Static Analysis Security Testing)**  
Automated security testing that analyzes source code for vulnerabilities without executing it. Part of secure development practices.

**Self-Documenting Code**  
Code written so clearly that it explains itself through good naming and structure, minimizing the need for comments. Comments should explain "why," not "what."

**Semantic HTML**  
HTML that uses elements for their intended meaning (e.g., `<header>`, `<nav>`, `<article>`) rather than generic `<div>` elements. Improves accessibility and maintainability.

**Service Discovery**  
A mechanism for services to find and communicate with each other without hardcoded addresses. Can use DNS, service registries, or service meshes.

**Service Mesh**  
Infrastructure layer that handles service-to-service communication, including load balancing, service discovery, and security. Adds complexity; only use when managing many services.

**SLI (Service Level Indicator)**  
A metric that measures service quality (e.g., request latency, error rate). Used to define SLOs.

**SLO (Service Level Objective)**  
A target for service reliability (e.g., "99.9% of requests complete successfully"). Based on SLIs and agreed with stakeholders.

**Snowflake Server**  
A server with unique, manually configured settings that differs from others. Hard to reproduce and maintain. Should be avoided in favor of standardized, automated configurations.

**SQL Injection**  
A security vulnerability where malicious SQL code is inserted into queries through user input. Prevented by using parameterized queries.

**Stateless Components**  
Components that don't store state between requests. Easier to scale horizontally because any instance can handle any request.

**Structured Logging**  
Logging in a structured format (typically JSON) that's easy to parse and search. Enables better log analysis and monitoring.

**Strong Consistency**  
A data consistency model where all reads receive the most recent write. Simpler to reason about but can be slower in distributed systems.

**SUS (System Usability Scale)**  
A standardized questionnaire for measuring usability. Users rate statements about the system, producing a usability score.

**Synchronous**  
Operations that block and wait for completion before continuing. Simpler to reason about but can be slower. Opposite of asynchronous.

## T

**Terraform / CloudFormation**  
Infrastructure as Code tools. Terraform is cloud-agnostic; CloudFormation is AWS-specific. Both allow defining infrastructure in code.

**Test Doubles**  
Generic term for mocks, stubs, and fakes used in testing to replace real dependencies. Enables isolated unit testing.

**Thundering Herd Problem**  
When many clients retry simultaneously after a service recovers, overwhelming it again. Prevented by using jitter in retry timing.

**TLS (Transport Layer Security)**  
Encryption protocol for securing data in transit over networks. Successor to SSL. Essential for protecting sensitive data.

**Transactions**  
Database operations that are atomic (all succeed or all fail). Ensures data consistency for multi-step operations.

## U

**UML (Unified Modeling Language)**  
A standard notation for visualizing software design. Can be complex; use simple UML basics for architecture diagrams.

**Unit Tests**  
Tests that verify individual units (functions, classes) work correctly in isolation. Fast and focused, but may not catch integration issues.

## V

**Vertical Scaling**  
Scaling by increasing resources of a single instance (more CPU, memory). Simpler but has limits. Generally prefer horizontal scaling.

**VPN (Virtual Private Network)**  
A secure network connection over the internet. Used to connect securely to private networks or services.

## W

**WCAG (Web Content Accessibility Guidelines)**  
International standards for making web content accessible to people with disabilities. Following WCAG ensures your application is usable by everyone.

---

## Common Phrases Explained

**"Design for query patterns, not theoretical flexibility"**  
Design your database schema based on how you actually query the data, not on theoretical future needs. For example, if you always query users by email, optimize for that pattern rather than creating a generic "search any field" structure you might never need.

**"Enterprise patterns without enterprise problems"**  
Adding complex, enterprise-grade solutions (like microservices, complex orchestration, etc.) when you don't have the actual problems they solve (team size, scale, etc.). Start simple and add complexity only when you have evidence it's needed.

**"Just in case"**  
Adding features, optimizations, or complexity based on hypothetical future needs rather than current evidence. Avoid this; build for current needs and optimize when you have real problems.

**"Measure before optimizing"**  
Always use profiling, monitoring, and analytics to identify actual bottlenecks and problems before attempting to optimize. Don't optimize based on assumptions.

**"Premature optimization"**  
Optimizing code before identifying actual bottlenecks through profiling. Often results in complex, hard-to-maintain code that doesn't improve performance.

**"Start simple, evolve gradually"**  
Begin with the simplest solution that meets current needs. Add complexity only when there's clear evidence it's needed (more users, actual bottlenecks, real failures).

