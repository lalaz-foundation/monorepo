# Glossary

Common terms and definitions used in the Lalaz Database package.

## A

### Aggregate Function
A SQL function that performs a calculation on a set of values and returns a single value. Examples: COUNT, SUM, AVG, MIN, MAX.

### Auto-Increment
A column attribute that automatically generates a unique integer value for each new row. Also known as SERIAL (PostgreSQL) or AUTO_INCREMENT (MySQL).

## B

### Batch
A group of migrations that are run together. Used for rollback operations to undo multiple related migrations at once.

### Binding
A parameter value that is safely substituted into a prepared statement. Prevents SQL injection.

### Blueprint
A class that collects table schema definitions (columns, indexes, foreign keys) before they are compiled to SQL.

## C

### Cascade
A referential action where changes to a parent row automatically propagate to related child rows. Common with ON DELETE CASCADE.

### Collation
The set of rules that determine how string comparison is performed. Examples: utf8mb4_unicode_ci, utf8_general_ci.

### Column Definition
A specification of a column's name, type, and modifiers (nullable, default, etc.).

### Connection
A wrapper around PDO that provides query execution, transaction management, and access to the query builder.

### Connection Manager
The component responsible for creating, pooling, and distributing database connections.

### Connection Pool
A cache of database connections that can be reused, avoiding the overhead of establishing new connections.

### Connector
A driver-specific class that creates PDO connections with appropriate configuration.

## D

### DDL (Data Definition Language)
SQL commands that define database structure: CREATE, ALTER, DROP, TRUNCATE.

### DML (Data Manipulation Language)
SQL commands that manipulate data: SELECT, INSERT, UPDATE, DELETE.

### Driver
The database type identifier: sqlite, mysql, postgres.

## E

### Expression
A raw SQL fragment that bypasses normal escaping. Used for complex SQL constructs.

## F

### Foreign Key
A constraint that enforces referential integrity between two tables by linking a column to a primary key in another table.

### Fluent Interface
A design pattern where methods return $this to enable method chaining.

## G

### Grammar
A class that compiles QueryBuilder or SchemaBuilder state into database-specific SQL syntax.

## H

### Having
A SQL clause for filtering grouped results, similar to WHERE but operates on aggregate values.

## I

### Index
A database structure that improves query performance on specific columns at the cost of additional storage and write overhead.

### Inner Join
A join that returns only rows where there is a match in both tables.

## J

### Join
A SQL operation that combines rows from two or more tables based on a related column.

### Join Clause
The specification of how two tables should be joined, including the type (inner, left, right) and conditions.

## L

### Left Join
A join that returns all rows from the left table and matched rows from the right table. Unmatched right table columns contain NULL.

### Lock
A mechanism to control concurrent access to rows. FOR UPDATE (exclusive) or LOCK IN SHARE MODE (shared).

## M

### Migration
A version-controlled change to the database schema, consisting of up() and down() methods.

### Migration Repository
A table that tracks which migrations have been run and their batch numbers.

### Migrator
The component that runs, rolls back, and manages migrations.

## N

### Nested Where
A grouped set of WHERE conditions that are evaluated together, typically used for complex logic with parentheses.

### Null
A special value representing missing or unknown data. Distinct from empty string or zero.

## O

### Offset
The number of rows to skip before returning results. Used with LIMIT for pagination.

### Order By
A SQL clause that sorts result rows by specified columns.

## P

### Pagination
The practice of dividing large result sets into smaller pages. Implemented using LIMIT and OFFSET.

### PDO (PHP Data Objects)
PHP's database abstraction layer that provides a consistent interface for accessing databases.

### Pool Status
Statistics about the connection pool: total connections, available connections, maximum limit.

### Prepared Statement
A precompiled SQL template where parameters are bound separately, providing security and performance benefits.

### Primary Key
A column or set of columns that uniquely identifies each row in a table.

## Q

### Query Builder
A fluent interface for constructing SQL queries programmatically.

### Query Event
A notification broadcast when a query is executed, containing SQL, bindings, duration, and other metadata.

### Query Listener
A callback function that receives query events for logging, debugging, or monitoring.

## R

### Raw Expression
A SQL fragment that is inserted directly into a query without escaping. See Expression.

### Read Replica
A database server that receives replicated data from the primary and handles read-only queries.

### Referential Integrity
The property that foreign key values must reference existing primary key values.

### Right Join
A join that returns all rows from the right table and matched rows from the left table.

### Rollback
The process of undoing a transaction or migration, reverting changes to a previous state.

## S

### Schema
The structure of a database, including tables, columns, indexes, and relationships.

### Schema Builder
A class that provides methods for creating, modifying, and dropping database tables.

### Seeder
A class that populates database tables with initial or test data.

### Seeder Runner
The component that executes seeder classes.

### Sticky Reads
A feature where read queries are routed to the primary database after a write operation to avoid replication lag issues.

### Subquery
A query nested inside another query, used in SELECT, FROM, WHERE, or JOIN clauses.

## T

### Transaction
A unit of work that is either fully completed (committed) or fully undone (rolled back).

### Transaction Isolation
The degree to which a transaction is isolated from other concurrent transactions.

## U

### Union
A SQL operation that combines the result sets of two or more SELECT statements.

### Unique Constraint
A constraint that ensures all values in a column (or combination of columns) are distinct.

### Unsigned
A numeric column attribute that only allows non-negative values, effectively doubling the positive range.

### Upsert
An operation that inserts a new row or updates an existing row if a unique constraint would be violated.

## W

### Where Clause
A SQL clause that filters rows based on specified conditions.

### Write Connection
A database connection to the primary server used for INSERT, UPDATE, DELETE operations.
