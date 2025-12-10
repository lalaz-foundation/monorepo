# Lalaz Reactive Documentation

Welcome to the Lalaz Reactive package documentation. This guide covers everything you need to know about building reactive, real-time components with PHP.

## Overview

The Reactive package provides a powerful system for building dynamic UI components that automatically synchronize state between server and client. It enables:

- **Server-Side State Management** - Keep your business logic on the server
- **Automatic Synchronization** - State changes automatically sync to the client
- **Event-Driven Architecture** - Components communicate through events
- **AJAX Integration** - Seamless HTTP handling for component updates

## Documentation Structure

- **[Quick Start](quick-start.md)** - Get up and running in minutes
- **[Installation](installation.md)** - Detailed installation instructions
- **[Concepts](concepts.md)** - Core concepts and architecture
- **[API Reference](api-reference.md)** - Complete API documentation
- **[Testing](testing.md)** - Testing your reactive components
- **[Glossary](glossary.md)** - Terms and definitions

## Quick Links

### Getting Started

1. [Installation](installation.md)
2. [Creating Your First Component](quick-start.md#creating-your-first-component)
3. [Understanding the Lifecycle](concepts.md#component-lifecycle)

### Core Features

- [State Management](concepts.md#state-management)
- [Events System](concepts.md#events-system)
- [Validation](concepts.md#validation)
- [Notifications](concepts.md#notifications)

### API

- [ReactiveComponent](api-reference.md#reactivecomponent)
- [ReactiveManager](api-reference.md#reactivemanager)
- [ComponentRegistry](api-reference.md#componentregistry)
- [ReactiveController](api-reference.md#reactivecontroller)

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                         Client                               │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────────┐   │
│  │  Component  │   │   Events    │   │  State Sync     │   │
│  │    HTML     │◄──│   Handler   │◄──│   (AJAX)        │   │
│  └─────────────┘   └─────────────┘   └────────┬────────┘   │
└───────────────────────────────────────────────│─────────────┘
                                                │
                                                ▼
┌─────────────────────────────────────────────────────────────┐
│                         Server                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                 ReactiveController                   │   │
│  │  ┌───────────┐  ┌───────────┐  ┌───────────────┐   │   │
│  │  │   call()  │  │  update() │  │    render()   │   │   │
│  │  └─────┬─────┘  └─────┬─────┘  └───────┬───────┘   │   │
│  └────────│──────────────│────────────────│───────────┘   │
│           │              │                │                │
│           ▼              ▼                ▼                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                 ReactiveManager                      │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │   │
│  │  │  mount() │  │restore() │  │ updateProperty() │  │   │
│  │  └────┬─────┘  └────┬─────┘  └────────┬─────────┘  │   │
│  └───────│─────────────│─────────────────│────────────┘   │
│          │             │                 │                 │
│          ▼             ▼                 ▼                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              ReactiveComponent                       │   │
│  │  ┌────────┐ ┌──────────┐ ┌────────┐ ┌───────────┐  │   │
│  │  │ State  │ │ Lifecycle│ │ Events │ │Validation │  │   │
│  │  └────────┘ └──────────┘ └────────┘ └───────────┘  │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## Version

Current version: 1.0.0

## License

MIT License - see [LICENSE](../LICENSE) for details.
