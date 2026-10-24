# VAPEHAUS ARCHITECTURAL FRAMEWORK

## Core Principles
1. **Strict Layer Separation**:
   - `Domain`: Pure enterprise rules, zero external dependencies.
   - `Execution`: Application use cases, orchestration, and business logic.
   - `Infrastructure`: External adapters, persistence (PDO), security, environment.
   - `Presentation`: HTTP transport, routing, middlewares, controller dispatchers.
   - Dependencies point inward only: Presentation -> Execution -> Domain <- Infrastructure.

2. **All Logic Goes to Execution**:
   - Controllers are thin dispatchers: they extract request inputs into DTOs, invoke the corresponding Execution Use Case, and serialize the returned DTO to an HTTP Response.
   - Domain models enforce entity invariants, but multi-entity orchestration, persistence, transactions, and side-effects must live exclusively inside `Execution` handlers.

3. **Deterministic Outputs**:
   - Every API endpoint returns a standardized JSON structure.
   - Success format:
     ```json
     {
       "success": true,
       "data": {},
       "meta": {}
     }
     ```
   - Error format:
     ```json
     {
       "success": false,
       "error": {
         "code": "ERROR_CODE",
         "message": "Human readable message",
         "details": []
       }
     }
     ```
   - No undefined states, non-deterministic random data, or unhandled exceptions leaking into responses.

4. **TDD Cycle (Red-Green-Refactor)**:
   - Tests specify expected behavior before or alongside production code.
   - Core domain rules and execution use cases must have automated test coverage.
