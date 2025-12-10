---
id: framework-index
title: "Lalaz Framework — Overview"
short_description: "A simple and modular PHP micro framework for web and console apps."
tags: ["framework","overview"]
version: "1.0"
type: "overview"
---

# Lalaz Framework — Overview

Quick facts:
- Designed for building small to medium web and CLI apps.
- Provides a dependency injection container, service provider system, routing, and a simple HTTP kernel.
- Focus: clarity and predictable behavior — great for beginners and small teams.

Why use Lalaz Framework?
- Simple container with auto-wiring
- Clear separation: service registration → provider boot → runtime
- Minimal surface area and easy to test

Next:
- Read Quick Start → quick-start.md
- Read Container → api/container.md

Common questions:
Q: How do I register services?
A: Use a ServiceProvider and implement register() to bind services into the container.
